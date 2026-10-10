<?php

namespace App\Modules\Marketplace\Application\Coverage;

use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;

interface LocalPersistedZoneSource
{
    /** @return list<ZoneCandidate>|null Null means no consultable draft market. */
    public function matches(MarketPublicId $market, GeographicPoint $point): ?array;
}
