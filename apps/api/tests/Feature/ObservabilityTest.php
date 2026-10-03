<?php

namespace Tests\Feature;

use App\Shared\Observability\ReportException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    public function test_http_and_job_context_is_preserved_sanitized_and_not_leaked_to_the_next_request(): void
    {
        $path = sys_get_temp_dir().'/traepe-logs-'.bin2hex(random_bytes(8)).'.jsonl';
        config(['logging.default' => 'safe', 'logging.channels.safe.path' => $path]);
        Log::forgetChannel('safe');
        Context::flush();
        Route::get('/api/v1/fixture-observability', function () {
            Context::add('email', 'fake-person@example.test');
            Context::addHidden('token', 'fake-hidden-token');
            Log::info('fake-password-message', ['authorization' => 'fake-bearer', 'nested' => ['payment' => 'fake-card']]);
            Queue::connection('sync')->push(new CorrelationFixtureJob);
            report(new RuntimeException('fake-exception-secret'));

            return response()->json(['correlation_id' => Context::get('correlation_id')]);
        });
        try {
            $id = (string) Str::ulid();
            $this->withHeader('X-Correlation-ID', $id)->getJson('/api/v1/fixture-observability')
                ->assertOk()->assertHeader('X-Correlation-ID', $id)->assertJsonPath('correlation_id', $id);
            $this->assertSame([], Context::all());
            $second = $this->withHeader('X-Correlation-ID', "invalid\r\nheader")->getJson('/api/v1/fixture-observability')->assertOk();
            $secondId = $second->json('correlation_id');
            $this->assertTrue(Str::isUlid($secondId));
            $this->assertNotSame($id, $secondId);
            $this->assertSame([], Context::all());
            $contents = file_get_contents($path);
            foreach (['fake-person', 'fake-hidden-token', 'fake-bearer', 'fake-card', 'fake-job-token', 'fake-exception-secret', 'fake-password-message'] as $canary) {
                $this->assertStringNotContainsString($canary, $contents);
            }
            $records = array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), array_filter(explode("\n", $contents)));
            $this->assertCount(10, $records);
            foreach ($records as $index => $record) {
                $this->assertSame($index < 5 ? $id : $secondId, $record['context']['correlation_id']);
                $this->assertArrayHasKey('datetime', $record);
                $this->assertSame([], $record['extra']);
            }
            $this->assertSame(2, count(array_filter($records, fn ($record) => $record['message'] === 'technical.probe')));
        } finally {
            Log::forgetChannel('safe');
            if (is_file($path)) {
                unlink($path);
            }
            Context::flush();
        }
    }

    public function test_queue_dehydration_and_hydration_drop_all_data_except_valid_correlation(): void
    {
        $id = (string) Str::ulid();
        Context::add(['correlation_id' => $id, 'password' => 'fake-secret']);
        Context::addHidden('email', 'fake-email@example.test');
        $payload = Context::dehydrate();
        $this->assertSame(['correlation_id'], array_keys($payload['data']));
        $this->assertSame([], $payload['hidden']);
        Context::hydrate($payload);
        $this->assertSame(['correlation_id' => $id], Context::all());
        Context::hydrate(['data' => ['correlation_id' => serialize('invalid'), 'secret' => serialize('fake-secret')]]);
        $this->assertTrue(Str::isUlid(Context::get('correlation_id')));
        $this->assertSame(['correlation_id'], array_keys(Context::all()));
        Context::flush();
    }

    public function test_exception_reporting_does_not_require_a_request_binding(): void
    {
        $path = sys_get_temp_dir().'/traepe-boot-'.bin2hex(random_bytes(8)).'.jsonl';
        config(['logging.default' => 'safe', 'logging.channels.safe.path' => $path]);
        Log::forgetChannel('safe');
        Context::flush();
        $request = app('request');
        unset($this->app['request']);
        try {
            app(ReportException::class)(new RuntimeException('fake-bootstrap-secret'));
            $contents = file_get_contents($path);
            $record = json_decode(trim($contents), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('job.failed', $record['message']);
            $this->assertTrue(Str::isUlid($record['context']['correlation_id']));
            $this->assertStringNotContainsString('fake-bootstrap-secret', $contents);
            $this->assertSame([], Context::all());
        } finally {
            $this->app->instance('request', $request);
            Log::forgetChannel('safe');
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

class CorrelationFixtureJob implements ShouldQueue
{
    public function handle(): void
    {
        Log::info('technical.probe', ['token' => 'fake-job-token']);
    }
}
