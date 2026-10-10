<?php

namespace App\Modules\Marketplace\Application\Fixtures;

use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;

interface LocalDraftFixtureStore
{
    public function create(FixtureProfile $profile, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): FixtureOperation;

    public function find(string $operationPublicId, string $actorPublicId): FixtureOperation;
}
