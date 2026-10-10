<?php

namespace App\Modules\Marketplace\Domain\Coverage;

final readonly class ZoneSelection
{
    private function __construct(public string $status, public ?string $zonePublicId) {}

    public static function selected(ZoneCandidate $candidate): self
    {
        return new self('selected', $candidate->publicId);
    }

    public static function outside(): self
    {
        return new self('outside', null);
    }

    public static function ambiguous(): self
    {
        return new self('ambiguous', null);
    }
}
