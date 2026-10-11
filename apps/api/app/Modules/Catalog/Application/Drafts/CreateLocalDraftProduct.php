<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CreateLocalDraftProduct
{
    public function __construct(private LocalDraftProductAccess $access, private LocalDraftProductWriter $writer) {}

    public function execute(int $authenticatedId, DraftProductInput $input, string $key, string $correlationId): DraftProductOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftProductAccess::CREATE, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }
        $authorize = function () use ($authenticatedId, $actor): void {
            if ($this->access->actor($authenticatedId, LocalDraftProductAccess::CREATE, new DateTimeImmutable('now', new DateTimeZone('UTC'))) !== $actor) {
                throw new DraftCatalogFailure('forbidden');
            }
        };

        return $this->writer->execute($input, $actor, $key, $correlationId, $authorize);
    }
}
