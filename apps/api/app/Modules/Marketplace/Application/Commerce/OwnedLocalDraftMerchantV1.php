<?php

namespace App\Modules\Marketplace\Application\Commerce;

interface OwnedLocalDraftMerchantV1
{
    /** Confirms and locks an owned 02I merchant within the caller's transaction. */
    public function lockOwnedDraft(string $merchantPublicId, string $actorPublicId): bool;
}
