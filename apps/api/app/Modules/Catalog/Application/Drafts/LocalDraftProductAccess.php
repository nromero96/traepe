<?php

namespace App\Modules\Catalog\Application\Drafts;

use DateTimeImmutable;

interface LocalDraftProductAccess
{
    public const CREATE = 'catalog.local.draft_product.create';

    public const CONTENT = 'catalog.local.draft_content.read';

    public const READ = 'catalog.local.draft_product.read';

    public const SCOPE_PUBLIC_ID = LocalDraftCatalogAccess::SCOPE_PUBLIC_ID;

    public const CREATE_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FB9';

    public const CONTENT_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBA';

    public const READ_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FBB';

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string;
}
