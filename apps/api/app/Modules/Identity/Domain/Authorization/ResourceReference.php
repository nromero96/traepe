<?php

namespace App\Modules\Identity\Domain\Authorization;

use InvalidArgumentException;

final readonly class ResourceReference
{
    public function __construct(public string $type, public string $publicId)
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $type) || ! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $publicId)) {
            throw new InvalidArgumentException('Invalid public resource reference.');
        }
    }

    public function matches(self $other): bool
    {
        return $this->type === $other->type && $this->publicId === $other->publicId;
    }
}
