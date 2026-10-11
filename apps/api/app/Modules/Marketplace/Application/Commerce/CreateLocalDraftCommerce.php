<?php

namespace App\Modules\Marketplace\Application\Commerce;

use App\Modules\Marketplace\Domain\Commerce\DraftCommerceFailure;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CreateLocalDraftCommerce
{
    public function __construct(private LocalDraftCommerceAccess $access, private LocalDraftCommerceWriter $writer) {}

    public function execute(int $authenticatedId, DraftCommerceInput $input, string $key, string $correlationId): DraftCommerceOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftCommerceAccess::CREATE, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCommerceFailure('forbidden');
        }
        $authorize = function () use ($authenticatedId, $actor): void {
            if ($this->access->actor($authenticatedId, LocalDraftCommerceAccess::CREATE, new DateTimeImmutable('now', new DateTimeZone('UTC'))) !== $actor) {
                throw new DraftCommerceFailure('forbidden');
            }
        };

        return $this->writer->execute($input, $actor, $key, $correlationId, $authorize);
    }
}
