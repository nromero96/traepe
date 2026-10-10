<?php

namespace Tests\Integration;

use App\Modules\Platform\Application\Delivery\EventPublisher;
use App\Modules\Platform\Application\Delivery\IdempotencyStore;
use App\Modules\Platform\Application\Delivery\OutboxDispatcher;
use App\Modules\Platform\Application\Delivery\TechnicalConsumer;
use App\Modules\Platform\Application\Delivery\TechnicalProbe;
use App\Modules\Platform\Application\Delivery\TechnicalProbeStore;
use App\Modules\Platform\Domain\Delivery\IdempotencyExpired;
use App\Modules\Platform\Domain\Delivery\IdempotencyMismatch;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DeliveryPrimitivesTest extends PostgresTestCase
{
    private function expiry(): DateTimeImmutable
    {
        return new DateTimeImmutable('+1 hour', new \DateTimeZone('UTC'));
    }

    private function produce(string $key = 'fixture-key'): string
    {
        return app(TechnicalProbe::class)->create($key, (string) Str::ulid(), $this->expiry(), ['fixture' => 1]);
    }

    public function test_replay_canonical_hash_mismatch_scope_actor_and_expiry_are_enforced(): void
    {
        $id = $this->produce();
        $this->assertSame($id, $this->produce());
        $this->assertSame(1, DB::table('platform_technical_operations')->count());
        $this->assertSame(1, DB::table('platform_outbox_messages')->count());
        try {
            app(TechnicalProbe::class)->create('fixture-key', (string) Str::ulid(), $this->expiry(), ['fixture' => 2]);
            $this->fail('Payload mismatch was accepted.');
        } catch (IdempotencyMismatch $exception) {
            $this->assertSame('idempotency_mismatch', $exception->getMessage());
        }
        $store = app(IdempotencyStore::class);
        $change = fn () => app(TechnicalProbeStore::class)->create((string) Str::ulid(), hash('sha256', 'fixture-key'));
        $a = $store->execute('scope.a', 'actor.a', 'fixture-key', ['b' => 2, 'a' => 1], $this->expiry(), $change);
        $this->assertSame($a, $store->execute('scope.a', 'actor.a', 'fixture-key', ['a' => 1, 'b' => 2], $this->expiry(), $change));
        $this->assertNotSame($a, $store->execute('scope.a', 'actor.b', 'fixture-key', ['a' => 1, 'b' => 2], $this->expiry(), $change));
        $this->assertNotSame($a, $store->execute('scope.b', 'actor.a', 'fixture-key', ['a' => 1, 'b' => 2], $this->expiry(), $change));
        DB::table('platform_idempotency_keys')->where('response_ref', $id)->update(['expires_at' => now()->subSecond()]);
        try {
            $this->produce();
            $this->fail('Expired key was reused.');
        } catch (IdempotencyExpired) {
            $this->assertSame(4, DB::table('platform_technical_operations')->count());
            $this->assertSame(4, DB::table('platform_idempotency_keys')->count());
        }
    }

    public function test_change_claim_and_outbox_roll_back_together(): void
    {
        try {
            app(IdempotencyStore::class)->execute('technical', 'system', 'rollback-key', [], $this->expiry(), function () {
                app(TechnicalProbeStore::class)->create((string) Str::ulid(), hash('sha256', 'rollback-key'));
                throw new RuntimeException('controlled_rollback');
            });
            $this->fail('Controlled rollback did not throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('controlled_rollback', $exception->getMessage());
        }
        foreach (['platform_idempotency_keys', 'platform_technical_operations', 'platform_outbox_messages'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertTrue(Str::isUlid($this->produce('rollback-key')));
    }

    public function test_database_rejects_mutating_event_content_identity_or_deleting_but_allows_delivery_metadata(): void
    {
        $this->produce();
        $row = DB::table('platform_outbox_messages')->first();
        foreach ([['event_id' => (string) Str::ulid()], ['content_ciphertext' => 'changed'], ['correlation_id' => (string) Str::ulid()], ['event_name' => 'changed.v1']] as $change) {
            try {
                DB::transaction(fn () => DB::table('platform_outbox_messages')->where('id', $row->id)->update($change));
                $this->fail('Immutable event update was accepted.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }
        }
        try {
            DB::transaction(fn () => DB::table('platform_outbox_messages')->where('id', $row->id)->delete());
            $this->fail('Immutable event deletion was accepted.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        }
        $this->assertSame(1, DB::table('platform_outbox_messages')->where('id', $row->id)->update(['attempts' => 1, 'next_attempt_at' => now(), 'last_error_code' => 'publication_failed']));
        $this->assertSame($row->content_ciphertext, DB::table('platform_outbox_messages')->first()->content_ciphertext);
    }

    public function test_publication_failure_retries_and_duplicate_delivery_has_a_single_transactional_effect(): void
    {
        $this->produce();
        $publisher = $this->mock(EventPublisher::class);
        $publisher->shouldReceive('publish')->once()->andThrow(new RuntimeException('fake-provider-secret'));
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatch());
        $row = DB::table('platform_outbox_messages')->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame('publication_failed', $row->last_error_code);
        $this->assertSame(1, $row->attempts);
        DB::table('platform_outbox_messages')->update(['next_attempt_at' => now()->subSecond()]);
        $publisher->shouldReceive('publish')->once()->with($row->event_id, $row->correlation_id)->andReturnNull();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatch());
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatch());
        $this->assertTrue(app(TechnicalConsumer::class)->consume($row->event_id));
        $this->assertFalse(app(TechnicalConsumer::class)->consume($row->event_id));
        $this->assertSame(1, DB::table('platform_technical_effects')->count());
        $this->assertSame(1, DB::table('platform_inbox_messages')->count());
        foreach ([['event_id' => $row->event_id, 'source' => 'other', 'message_id' => (string) Str::ulid()],
            ['event_id' => (string) Str::ulid(), 'source' => 'platform.technical.v1', 'message_id' => $row->event_id]] as $identity) {
            try {
                DB::transaction(fn () => DB::table('platform_inbox_messages')->insert($identity + ['content_hash' => $row->content_hash, 'correlation_id' => $row->correlation_id]));
                $this->fail('Inbox uniqueness violation was accepted.');
            } catch (QueryException $exception) {
                $this->assertSame('23505', $exception->errorInfo[0]);
            }
        }
    }

    public function test_inbox_and_effect_rollback_when_the_consumer_cannot_apply_the_change(): void
    {
        $operation = $this->produce();
        $row = DB::table('platform_outbox_messages')->first();
        DB::table('platform_technical_operations')->where('public_id', $operation)->delete();
        try {
            app(TechnicalConsumer::class)->consume($row->event_id);
            $this->fail('Missing operation was accepted.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->errorInfo[0]);
        }
        $this->assertSame(0, DB::table('platform_inbox_messages')->count());
        $this->assertSame(0, DB::table('platform_technical_effects')->count());
        DB::table('platform_technical_operations')->insert(['public_id' => $operation, 'correlation_id' => $row->correlation_id]);
        $this->assertTrue(app(TechnicalConsumer::class)->consume($row->event_id));
        $this->assertSame(1, DB::table('platform_technical_effects')->count());
    }

    public function test_two_independent_processes_race_on_the_same_key_then_on_the_same_event(): void
    {
        $id = (string) Str::ulid();
        $runPair = function (string $action, string $value) use ($id): array {
            $processes = [];
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/delivery-race.php'), $action, $value, $id, $this->probeDatabase], base_path(), ['LOG_CHANNEL' => 'safe']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), 'Race child failed; provider details suppressed.');
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        };
        $results = $runPair('produce', 'concurrent-key');
        $this->assertSame($results[0]['reference'], $results[1]['reference']);
        $this->assertSame(1, DB::table('platform_technical_operations')->count());
        $this->assertSame(1, DB::table('platform_outbox_messages')->count());
        $event = DB::table('platform_outbox_messages')->value('event_id');
        DB::statement('CREATE TABLE platform_test_publications (event_id platform_ulid PRIMARY KEY)');
        $publications = $runPair('dispatch', '1');
        $this->assertSame(1, array_sum(array_column($publications, 'published')));
        $this->assertSame(1, DB::table('platform_test_publications')->count());
        $results = $runPair('consume', $event);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['changed'])));
        $this->assertSame(1, DB::table('platform_inbox_messages')->count());
        $this->assertSame(1, DB::table('platform_technical_effects')->count());
    }

    public function test_migration_reapplication_and_invalid_public_identifiers_are_checked(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        try {
            DB::transaction(fn () => DB::table('platform_technical_operations')->insert(['public_id' => 'not-a-ulid', 'correlation_id' => (string) Str::ulid()]));
            $this->fail('Invalid public identifier was accepted.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        }
        // Target Platform explicitly; newer Identity history is forward-only.
        $batch = DB::table('migrations')->where('migration', '2026_10_03_000000_create_platform_delivery_primitives')->value('batch');
        $this->assertSame(0, Artisan::call('migrate:rollback', [
            '--batch' => $batch, '--path' => ['database/migrations/2026_10_03_000000_create_platform_delivery_primitives.php'], '--force' => true,
        ]));
        $this->assertFalse(Schema::hasTable('platform_outbox_messages'));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertTrue(Schema::hasTable('platform_outbox_messages'));
    }
}
