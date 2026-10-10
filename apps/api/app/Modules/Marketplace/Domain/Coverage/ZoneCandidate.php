<?php

namespace App\Modules\Marketplace\Domain\Coverage;

use InvalidArgumentException;

final readonly class ZoneCandidate
{
    public function __construct(public string $publicId, public int $priority)
    {
        if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $publicId)) {
            throw new InvalidArgumentException('Invalid public zone reference.');
        }
    }
}
