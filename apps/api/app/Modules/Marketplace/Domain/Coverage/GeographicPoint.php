<?php

namespace App\Modules\Marketplace\Domain\Coverage;

use InvalidArgumentException;

final readonly class GeographicPoint
{
    public function __construct(public float $longitude, public float $latitude)
    {
        if (! is_finite($longitude) || ! is_finite($latitude) || $longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Invalid WGS84 point.');
        }
    }
}
