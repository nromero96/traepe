<?php

namespace App\Modules\Platform\Domain\Delivery;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TechnicalEvent
{
    public const NAME = 'platform.technical_probe.v1';

    /** @param array<string, mixed> $envelope */
    public function __construct(public array $envelope)
    {
        $fields = ['event_id', 'event_name', 'version', 'aggregate', 'occurred_at', 'recorded_at', 'actor', 'correlation_id', 'causation_id', 'idempotency_key_hash', 'context', 'payload', 'metadata'];
        $actual = array_keys($envelope);
        sort($actual);
        sort($fields);
        if ($actual !== $fields || ($envelope['event_name'] ?? null) !== self::NAME || ($envelope['version'] ?? null) !== 1 ||
            ($envelope['aggregate']['type'] ?? null) !== 'technical_probe' ||
            ! self::sameMap($envelope['aggregate'] ?? null, ['type' => 'technical_probe', 'public_id' => $envelope['aggregate']['public_id'] ?? null]) ||
            ! self::sameMap($envelope['actor'] ?? null, ['type' => 'system', 'public_id' => null]) ||
            ! self::sameMap($envelope['context'] ?? null, ['market_id' => null, 'branch_id' => null]) ||
            ($envelope['causation_id'] ?? null) !== null || ! self::sameMap($envelope['metadata'] ?? null, ['schema_version' => 1]) ||
            ! is_array($envelope['payload'] ?? null) ||
            array_keys($envelope['payload']) !== ['operation_id'] ||
            ! preg_match('/\A[a-f0-9]{64}\z/', $envelope['idempotency_key_hash'] ?? '')) {
            throw new InvalidArgumentException('technical_event_invalid');
        }
        foreach ([$envelope['event_id'], $envelope['correlation_id'], $envelope['aggregate']['public_id'] ?? null, $envelope['payload']['operation_id']] as $id) {
            if (! is_string($id) || ! preg_match('/\A[0-7][0-9A-HJKMNP-TV-Z]{25}\z/', $id)) {
                throw new InvalidArgumentException('technical_event_invalid');
            }
        }
        if ($envelope['aggregate']['public_id'] !== $envelope['payload']['operation_id']) {
            throw new InvalidArgumentException('technical_event_invalid');
        }
        foreach (['occurred_at', 'recorded_at'] as $field) {
            if (! is_string($envelope[$field])) {
                throw new InvalidArgumentException('technical_event_invalid');
            }
            $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $envelope[$field]);
            if (! $date || $date->getOffset() !== 0 || $date->format('Y-m-d\TH:i:sP') !== $envelope[$field]) {
                throw new InvalidArgumentException('technical_event_invalid');
            }
        }
    }

    /** @param array<string, mixed> $expected */
    private static function sameMap(mixed $value, array $expected): bool
    {
        if (! is_array($value)) {
            return false;
        }
        ksort($value);
        ksort($expected);

        return $value === $expected;
    }
}
