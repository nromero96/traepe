<?php

namespace App\Modules\Marketplace\Domain\Commerce;

use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;

final readonly class CommercialContext
{
    public function __construct(
        public MerchantPublicId $merchant,
        public MarketPublicId $market,
        public BranchPublicId $branch,
    ) {}
}
