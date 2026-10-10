<?php

namespace App\Modules\Marketplace\Domain\Fixtures;

enum FixtureProfile: string
{
    case A = 'synthetic-origin-a-v1';
    case B = 'synthetic-origin-b-v1';

    public function marketName(): string
    {
        return $this === self::A ? 'Synthetic Market A' : 'Synthetic Market B';
    }
}
