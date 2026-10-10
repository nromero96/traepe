<?php

namespace App\Modules\Identity\Domain\Authorization;

use InvalidArgumentException;

final readonly class Scope
{
    public function __construct(public string $type, public string $publicId)
    {
        if (! in_array($type, ['platform', 'merchant', 'branch'], true) || ! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $publicId)) {
            throw new InvalidArgumentException('Invalid public scope reference.');
        }
    }

    public function matches(self $other): bool
    {
        return $this->type === $other->type && $this->publicId === $other->publicId;
    }
}
