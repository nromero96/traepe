<?php

use App\Modules\Platform\Application\Delivery\EventPublisher;
use App\Modules\Platform\Application\Delivery\OutboxDispatcher;
use App\Modules\Platform\Application\Delivery\TechnicalConsumer;
use App\Modules\Platform\Application\Delivery\TechnicalProbe;
use App\Modules\Platform\Application\Delivery\TechnicalProbeStore;
use App\Modules\Platform\Infrastructure\Delivery\PostgresTechnicalProbeStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    [$script, $action, $value, $correlation, $database] = $argv;
    if (! preg_match('/\Atraepe_00e_test_[a-f0-9]{16}\z/', $database)) {
        throw new RuntimeException('Invalid isolated database.');
    }
    config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => $database]);
    DB::purge('pgsql');
    if ($action === 'produce') {
        $app->instance(TechnicalProbeStore::class, new class implements TechnicalProbeStore
        {
            public function create(string $correlationId, string $keyHash): string
            {
                usleep(400_000); // Hold the winning transaction while the second process races.

                return (new PostgresTechnicalProbeStore)->create($correlationId, $keyHash);
            }
        });
        $reference = app(TechnicalProbe::class)->create($value, $correlation, new DateTimeImmutable('+1 hour', new DateTimeZone('UTC')), ['fixture' => 1]);
        echo json_encode(['reference' => $reference], JSON_THROW_ON_ERROR);
    } elseif ($action === 'dispatch') {
        $app->instance(EventPublisher::class, new class implements EventPublisher
        {
            public function publish(string $eventId, string $correlationId): void
            {
                usleep(400_000);
                DB::table('platform_test_publications')->insert(['event_id' => $eventId]);
            }
        });
        echo json_encode(['published' => app(OutboxDispatcher::class)->dispatch(1)], JSON_THROW_ON_ERROR);
    } elseif ($action === 'consume') {
        echo json_encode(['changed' => app(TechnicalConsumer::class)->consume($value)], JSON_THROW_ON_ERROR);
    } else {
        throw new RuntimeException('Invalid race action.');
    }
} catch (Throwable) {
    fwrite(STDERR, "Race probe failed; provider details suppressed.\n");
    exit(1);
}
