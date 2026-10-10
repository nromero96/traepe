<?php

namespace App\Modules\Marketplace\Application\Coverage;

use App\Modules\Marketplace\Domain\Coverage\ZoneSelection;

final readonly class LocalPersistedCoverageResult
{
    private function __construct(public string $status, public ?string $zonePublicId) {}

    public static function marketNotFound(): self
    {
        return new self('market_not_found', null);
    }

    public static function fromSelection(ZoneSelection $selection): self
    {
        return new self($selection->status, $selection->zonePublicId);
    }
}
