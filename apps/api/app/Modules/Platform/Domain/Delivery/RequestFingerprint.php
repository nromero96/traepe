<?php

namespace App\Modules\Platform\Domain\Delivery;

use InvalidArgumentException;

final class RequestFingerprint
{
    /** @param array<array-key, mixed> $payload */
    public static function hash(array $payload): string
    {
        return hash('sha256', json_encode(self::canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }

            return array_map(self::canonical(...), $value);
        }
        if (! is_null($value) && ! is_string($value) && ! is_int($value) && ! is_bool($value)) {
            throw new InvalidArgumentException('Payload must use explicit JSON primitives without floats or objects.');
        }

        return $value;
    }
}
