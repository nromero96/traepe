<?php

use App\Modules\Platform\Application\Delivery\TechnicalProbe;
use App\Modules\Platform\Interfaces\Jobs\ConsumeTechnicalEvent;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\CorrelationId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\JobRepository;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function deliveryAssert(bool $value, string $message): void
{
    if (! $value) {
        throw new RuntimeException($message);
    }
    echo 'PASS: '.$message."\n";
}

try {
    $key = '00e-probe-'.bin2hex(random_bytes(8));
    $correlation = (string) Str::ulid();
    $expiry = new DateTimeImmutable('+1 hour', new DateTimeZone('UTC'));
    $operation = null;
    $request = Request::create('/api/v1/fixture-only-not-registered', 'POST', [], [], [], ['HTTP_X_CORRELATION_ID' => $correlation]);
    (new CorrelationId)->handle($request, function (Request $request) use ($key, $expiry, &$operation) {
        $operation = app(TechnicalProbe::class)->create($key, $request->attributes->get('correlation_id'), $expiry, ['fixture' => 1]);
        Log::info('technical.probe', ['operation_id' => $operation, 'password' => 'fake-private-canary']);
        deliveryAssert(Artisan::call('platform:dispatch-outbox', ['--limit' => 100]) === 0, 'publication batch queued from request context');

        return ApiResponse::resource($request, 'technical_probe', $operation, []);
    });
    deliveryAssert(Context::all() === [], 'request context cleaned after dispatch');
    deliveryAssert(app(TechnicalProbe::class)->create($key, (string) Str::ulid(), $expiry, ['fixture' => 1]) === $operation, 'real producer returns identical reference on replay');
    $event = DB::table('platform_outbox_messages')->where('aggregate_id', $operation)->first();
    $deadline = microtime(true) + 25;
    do {
        $effect = DB::table('platform_technical_effects')->where('event_id', $event->event_id)->first();
        $published = DB::table('platform_outbox_messages')->where('event_id', $event->event_id)->value('status') === 'published';
        if ($effect && $published) {
            break;
        }
        usleep(200_000);
    } while (microtime(true) < $deadline);
    deliveryAssert($effect !== null && $published, 'Horizon publishes outbox and applies inbox effect');
    deliveryAssert($effect->correlation_id === $correlation, 'request correlation reaches persisted consumer effect');
    $retry = Context::scope(fn () => Queue::connection('redis')->push(new ConsumeTechnicalEvent($event->event_id), '', 'technical'), ['correlation_id' => $correlation]);
    $deadline = microtime(true) + 25;
    do {
        $job = app(JobRepository::class)->getJobs([$retry])->first();
        if ($job && $job->status === 'completed') {
            break;
        }
        usleep(200_000);
    } while (microtime(true) < $deadline);
    deliveryAssert($job !== null && $job->status === 'completed', 'redelivery actually consumed by Horizon');
    deliveryAssert(DB::table('platform_technical_effects')->where('event_id', $event->event_id)->count() === 1, 'redelivery has exactly one observable effect');
    $records = [];
    foreach (file(storage_path('logs/technical.jsonl'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if (($record['context']['correlation_id'] ?? null) === $correlation) {
            $records[] = $record;
        }
    }
    $messages = array_column($records, 'message');
    deliveryAssert(in_array('technical.probe', $messages, true) && in_array('http.completed', $messages, true) && in_array('job.processed', $messages, true) && in_array('inbox.consumed', $messages, true) && in_array('outbox.published', $messages, true), 'request-to-job-to-event logs share correlation');
    deliveryAssert(! str_contains(json_encode($records), 'fake-private-canary'), 'logs redact forbidden request data');
    echo "Technical records retained for review; no production data or unregistered HTTP endpoint created.\n";
} catch (Throwable) {
    fwrite(STDERR, "Delivery probe failed; provider details suppressed.\n");
    exit(1);
}
