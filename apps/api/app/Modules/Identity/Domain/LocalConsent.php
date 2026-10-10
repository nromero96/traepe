<?php

namespace App\Modules\Identity\Domain;

use InvalidArgumentException;

final readonly class LocalConsent
{
    public const VERSION = 'local-v1';

    public function __construct(bool $accepted, public string $version = self::VERSION)
    {
        if (! $accepted || $version !== self::VERSION) {
            throw new InvalidArgumentException('Explicit local consent required.');
        }
    }
}
