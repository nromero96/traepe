<?php

namespace App\Modules\Marketplace\Application\Commerce;

use App\Modules\Marketplace\Domain\Commerce\CommercialContext;

interface LocalCommercialContextSource
{
    public function matches(CommercialContext $context): bool;
}
