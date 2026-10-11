<?php

namespace App\Modules\Marketplace\Application\Commerce;

use App\Modules\Marketplace\Domain\Commerce\DraftCommerceFailure;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReadLocalDraftCommerce
{
    public function __construct(private LocalDraftCommerceAccess $access, private LocalDraftCommerceStore $store) {}

    public function execute(int $authenticatedId, string $operationPublicId): DraftCommerceOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftCommerceAccess::READ, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCommerceFailure('forbidden');
        }

        return $this->store->find($operationPublicId, $actor);
    }
}
