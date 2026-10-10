<?php

namespace App\Modules\Identity\Domain;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^\+[1-9][0-9]{1,14}$/D', $value)) {
            throw new InvalidArgumentException('Invalid international phone format.');
        }
    }
}
