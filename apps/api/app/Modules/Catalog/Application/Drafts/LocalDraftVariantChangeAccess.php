<?php

namespace App\Modules\Catalog\Application\Drafts;

use DateTimeImmutable;

interface LocalDraftVariantChangeAccess
{
    public const UPDATE = 'catalog.local.draft_variant.update';

    public const READ = 'catalog.local.draft_variant_change.read';

    public const SCOPE_PUBLIC_ID = LocalDraftCatalogAccess::SCOPE_PUBLIC_ID;

    public const UPDATE_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBH';

    public const READ_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBJ';

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string;
}
