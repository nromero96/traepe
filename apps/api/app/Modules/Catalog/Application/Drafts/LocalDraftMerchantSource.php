<?php

namespace App\Modules\Catalog\Application\Drafts;

interface LocalDraftMerchantSource
{
    public function lockOwnedDraft(string $merchantPublicId, string $actorPublicId): bool;
}
