<?php

namespace App\Modules\Marketplace\Application\Coverage;

use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;

interface LocalZoneSource
{
    /** @return list<ZoneCandidate> */
    public function matches(GeographicPoint $point): array;
}
