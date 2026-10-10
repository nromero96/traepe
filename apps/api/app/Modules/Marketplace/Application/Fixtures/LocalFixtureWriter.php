<?php

namespace App\Modules\Marketplace\Application\Fixtures;

use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use Closure;

interface LocalFixtureWriter
{
    /** @param Closure(): void $authorize */
    public function execute(FixtureProfile $profile, string $actorPublicId, string $key, string $correlationId, Closure $authorize): FixtureOperation;
}
