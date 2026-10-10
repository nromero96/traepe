<?php

namespace App\Modules\Marketplace\Domain\Commerce;

use InvalidArgumentException;

final readonly class BranchPublicId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $value)) {
            throw new InvalidArgumentException('Invalid public branch reference.');
        }
    }
}
