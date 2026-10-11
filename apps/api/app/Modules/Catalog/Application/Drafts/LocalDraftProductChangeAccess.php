<?php

namespace App\Modules\Catalog\Application\Drafts;

use DateTimeImmutable;

interface LocalDraftProductChangeAccess
{
    public const UPDATE = 'catalog.local.draft_product.update';

    public const READ = 'catalog.local.draft_product_change.read';

    public const SCOPE_PUBLIC_ID = LocalDraftCatalogAccess::SCOPE_PUBLIC_ID;

    public const UPDATE_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBC';

    public const READ_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBD';

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string;
}
