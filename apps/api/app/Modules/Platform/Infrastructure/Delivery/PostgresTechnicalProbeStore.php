<?php

namespace App\Modules\Platform\Infrastructure\Delivery;

use App\Modules\Platform\Application\Delivery\TechnicalProbeStore;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use App\Modules\Platform\Domain\Delivery\TechnicalEvent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class PostgresTechnicalProbeStore implements TechnicalProbeStore
{
    public function create(string $correlationId, string $keyHash): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Technical change and event require a transaction.');
        }
        $operation = (string) Str::ulid();
        $event = (string) Str::ulid();
        $now = now()->utc()->toIso8601String();
        $envelope = [
            'event_id' => $event, 'event_name' => TechnicalEvent::NAME, 'version' => 1,
            'aggregate' => ['type' => 'technical_probe', 'public_id' => $operation],
            'occurred_at' => $now, 'recorded_at' => $now,
            'actor' => ['type' => 'system', 'public_id' => null],
            'correlation_id' => $correlationId, 'causation_id' => null,
            'idempotency_key_hash' => $keyHash,
            'context' => ['market_id' => null, 'branch_id' => null],
            'payload' => ['operation_id' => $operation], 'metadata' => ['schema_version' => 1],
        ];
        new TechnicalEvent($envelope);
        DB::table('platform_technical_operations')->insert(['public_id' => $operation, 'correlation_id' => $correlationId]);
        DB::table('platform_outbox_messages')->insert([
            'event_id' => $event, 'event_name' => $envelope['event_name'], 'version' => 1,
            'aggregate_type' => 'technical_probe', 'aggregate_id' => $operation,
            'correlation_id' => $correlationId, 'occurred_at' => $now, 'recorded_at' => $now,
            'content_ciphertext' => Crypt::encryptString(json_encode($envelope, JSON_THROW_ON_ERROR)),
            'content_hash' => RequestFingerprint::hash($envelope),
        ]);

        return $operation;
    }
}
