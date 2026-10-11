<?php

namespace App\Modules\Marketplace\Application\Commerce;

use DateTimeImmutable;

interface LocalDraftCommerceAccess
{
    public const CREATE = 'marketplace.local.draft_commerce.create';

    public const READ = 'marketplace.local.draft_commerce.read';

    public const SCOPE_PUBLIC_ID = '01ARZ3NDEKTSV4RRFFQ69G5FB2';

    public const CREATE_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FB5';

    public const READ_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FB6';

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string;
}
