<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Marketplace\Application\Commerce\OwnedLocalDraftMerchantV1;

final readonly class MarketplaceLocalDraftMerchantSource implements LocalDraftMerchantSource
{
    public function __construct(private OwnedLocalDraftMerchantV1 $merchants) {}

    public function lockOwnedDraft(string $merchantPublicId, string $actorPublicId): bool
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return $this->merchants->lockOwnedDraft($merchantPublicId, $actorPublicId);
    }
}
