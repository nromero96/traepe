<?php

namespace App\Modules\Platform\Infrastructure\Delivery;

use App\Modules\Platform\Application\Delivery\TechnicalConsumer;
use App\Modules\Platform\Domain\Delivery\IdempotencyMismatch;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use App\Modules\Platform\Domain\Delivery\TechnicalEvent;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class PostgresTechnicalConsumer implements TechnicalConsumer
{
    public function consume(string $eventId): bool
    {
        $row = DB::table('platform_outbox_messages')->where('event_id', $eventId)->first();
        if (! $row) {
            throw new RuntimeException('technical_event_missing');
        }
        $envelope = json_decode(Crypt::decryptString($row->content_ciphertext), true, flags: JSON_THROW_ON_ERROR);
        new TechnicalEvent($envelope);
        if ($row->event_name !== TechnicalEvent::NAME || $row->version !== 1 ||
            ($envelope['event_id'] ?? null) !== $eventId || ($envelope['version'] ?? null) !== 1 ||
            ($envelope['correlation_id'] ?? null) !== $row->correlation_id ||
            ($envelope['payload']['operation_id'] ?? null) !== $row->aggregate_id ||
            ! hash_equals($row->content_hash, RequestFingerprint::hash($envelope))) {
            throw new RuntimeException('technical_event_invalid');
        }

        return Context::scope(fn () => DB::transaction(function () use ($row): bool {
            $inserted = DB::table('platform_inbox_messages')->insertOrIgnore([
                'event_id' => $row->event_id, 'source' => 'platform.technical.v1', 'message_id' => $row->event_id,
                'content_hash' => $row->content_hash, 'correlation_id' => $row->correlation_id,
            ]);
            $inbox = DB::table('platform_inbox_messages')->where('event_id', $row->event_id)->lockForUpdate()->first();
            if (! $inbox || ! hash_equals($inbox->content_hash, $row->content_hash)) {
                throw new IdempotencyMismatch('inbox_message_mismatch');
            }
            if (! $inserted) {
                return false;
            }
            DB::table('platform_technical_effects')->insert([
                'public_id' => (string) Str::ulid(), 'event_id' => $row->event_id,
                'operation_id' => $row->aggregate_id, 'correlation_id' => $row->correlation_id,
            ]);
            Log::info('inbox.consumed', ['event_id' => $row->event_id, 'operation_id' => $row->aggregate_id]);

            return true;
        }, 3), ['correlation_id' => $row->correlation_id]);
    }
}
