<?php

namespace App\Modules\Marketplace\Application\Fixtures;

use DateTimeImmutable;

interface LocalDraftFixtureAccess
{
    public const CAPABILITY = 'marketplace.local.draft_fixture.create';

    public const SCOPE_PUBLIC_ID = '01ARZ3NDEKTSV4RRFFQ69G5FB2';

    public const RESOURCE_PUBLIC_ID = '01ARZ3NDEKTSV4RRFFQ69G5FB4';

    public function actor(int $authenticatedId, DateTimeImmutable $serverNow): ?string;
}
