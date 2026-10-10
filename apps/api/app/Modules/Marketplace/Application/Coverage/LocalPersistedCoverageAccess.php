<?php

namespace App\Modules\Marketplace\Application\Coverage;

use DateTimeImmutable;

/** Technical fixture references approved by DP-030; never operational permissions. */
interface LocalPersistedCoverageAccess
{
    public const CAPABILITY = 'marketplace.local.persisted_coverage.read';

    public const SCOPE_PUBLIC_ID = '01ARZ3NDEKTSV4RRFFQ69G5FB2';

    public const RESOURCE_PUBLIC_ID = '01ARZ3NDEKTSV4RRFFQ69G5FB3';

    public function allows(int $authenticatedId, DateTimeImmutable $serverNow): bool;
}
