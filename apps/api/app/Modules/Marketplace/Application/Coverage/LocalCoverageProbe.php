<?php

namespace App\Modules\Marketplace\Application\Coverage;

use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\LocalZoneSelectionPolicy;
use App\Modules\Marketplace\Domain\Coverage\ZoneSelection;

final readonly class LocalCoverageProbe
{
    public const VERSION = 'local-coverage-v1';

    public function __construct(private LocalZoneSource $source) {}

    public function evaluate(GeographicPoint $point): ZoneSelection
    {
        return (new LocalZoneSelectionPolicy)->select($this->source->matches($point));
    }
}
