<?php

namespace App\Modules\Catalog\Application\Drafts;

use DateTimeImmutable;

interface LocalDraftVariantAccess
{
    public const CREATE = 'catalog.local.draft_variant.create';

    public const CONTENT = 'catalog.local.draft_variant.read_current';

    public const READ = 'catalog.local.draft_variant.read';

    public const SCOPE_PUBLIC_ID = LocalDraftCatalogAccess::SCOPE_PUBLIC_ID;

    public const CREATE_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBE';

    public const CONTENT_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBF';

    public const READ_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBG';

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string;
}
