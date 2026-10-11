<?php

namespace App\Modules\Catalog\Application\Drafts;

use DateTimeImmutable;

interface LocalDraftCatalogAccess
{
    public const CREATE = 'catalog.local.draft_catalog.create';

    public const READ = 'catalog.local.draft_catalog.read';

    public const SCOPE_PUBLIC_ID = '01ARZ3NDEKTSV4RRFFQ69G5FB2';

    public const CREATE_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FB7';

    public const READ_RESOURCE = '01ARZ3NDEKTSV4RRFFQ69G5FB8';

    public function actor(int $authenticatedId, string $capability, DateTimeImmutable $serverNow): ?string;
}
