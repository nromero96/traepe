<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class UpdateLocalDraftProduct
{
    public function __construct(private LocalDraftProductChangeAccess $access, private LocalDraftProductChangeWriter $writer) {}

    public function execute(int $authenticatedId, DraftProductChangeInput $input, string $key, string $correlationId): DraftProductChangeOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftProductChangeAccess::UPDATE, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }
        $authorize = function () use ($authenticatedId, $actor): void {
            if ($this->access->actor($authenticatedId, LocalDraftProductChangeAccess::UPDATE, new DateTimeImmutable('now', new DateTimeZone('UTC'))) !== $actor) {
                throw new DraftCatalogFailure('forbidden');
            }
        };

        return $this->writer->execute($input, $actor, $key, $correlationId, $authorize);
    }
}
