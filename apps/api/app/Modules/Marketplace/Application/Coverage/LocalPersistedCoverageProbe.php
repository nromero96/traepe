<?php

namespace App\Modules\Marketplace\Application\Coverage;

use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\LocalZoneSelectionPolicy;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;

final readonly class LocalPersistedCoverageProbe
{
    public const VERSION = 'local-persisted-coverage-v1';

    public function __construct(private LocalPersistedZoneSource $source) {}

    public function evaluate(MarketPublicId $market, GeographicPoint $point): LocalPersistedCoverageResult
    {
        $candidates = $this->source->matches($market, $point);

        return $candidates === null
            ? LocalPersistedCoverageResult::marketNotFound()
            : LocalPersistedCoverageResult::fromSelection((new LocalZoneSelectionPolicy)->select($candidates));
    }
}
