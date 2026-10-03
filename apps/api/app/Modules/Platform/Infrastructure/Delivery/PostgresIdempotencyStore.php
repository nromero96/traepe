<?php

namespace App\Modules\Platform\Infrastructure\Delivery;

use App\Modules\Platform\Application\Delivery\IdempotencyStore;
use App\Modules\Platform\Domain\Delivery\IdempotencyExpired;
use App\Modules\Platform\Domain\Delivery\IdempotencyMismatch;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PostgresIdempotencyStore implements IdempotencyStore
{
    /**
     * @param  array<array-key, mixed>  $payload
     * @param  Closure(): mixed  $change
     */
    public function execute(string $scope, string $actor, string $key, array $payload, DateTimeImmutable $expiresAt, Closure $change): string
    {
        if ($scope === '' || $actor === '' || $key === '' || strlen($key) > 255 || $expiresAt->getOffset() !== 0 || $expiresAt <= new DateTimeImmutable('now')) {
            throw new InvalidArgumentException('Explicit scope, actor, bounded key and future UTC expiry are required.');
        }
        $identity = ['scope_hash' => hash('sha256', $scope), 'actor_hash' => hash('sha256', $actor), 'key_hash' => hash('sha256', $key)];
        $hash = RequestFingerprint::hash($payload);

        return DB::transaction(function () use ($identity, $hash, $expiresAt, $change): string {
            DB::table('platform_idempotency_keys')->insertOrIgnore($identity + [
                'public_id' => (string) Str::ulid(), 'request_hash' => $hash,
                'state' => 'processing', 'expires_at' => $expiresAt->format('Y-m-d H:i:s.uP'),
            ]);
            $claim = DB::table('platform_idempotency_keys')->where($identity)->lockForUpdate()->first();
            if ($claim === null) {
                throw new \LogicException('idempotency_claim_missing');
            }
            if (! hash_equals($claim->request_hash, $hash)) {
                throw new IdempotencyMismatch('idempotency_mismatch');
            }
            if (new DateTimeImmutable($claim->expires_at) <= new DateTimeImmutable('now')) {
                throw new IdempotencyExpired('idempotency_expired');
            }
            if ($claim->state === 'completed') {
                return $claim->response_ref;
            }
            $reference = $change();
            if (! is_string($reference) || ! Str::isUlid($reference)) {
                throw new InvalidArgumentException('A public response reference is required.');
            }
            DB::table('platform_idempotency_keys')->where('id', $claim->id)->update(['state' => 'completed', 'response_ref' => $reference]);

            return $reference;
        }, 3);
    }
}
