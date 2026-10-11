<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CreateLocalDraftCatalog
{
    public function __construct(private LocalDraftCatalogAccess $access, private LocalDraftCatalogWriter $writer) {}

    public function execute(int $authenticatedId, DraftCatalogInput $input, string $key, string $correlationId): DraftCatalogOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftCatalogAccess::CREATE, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }
        $authorize = function () use ($authenticatedId, $actor): void {
            if ($this->access->actor($authenticatedId, LocalDraftCatalogAccess::CREATE, new DateTimeImmutable('now', new DateTimeZone('UTC'))) !== $actor) {
                throw new DraftCatalogFailure('forbidden');
            }
        };

        return $this->writer->execute($input, $actor, $key, $correlationId, $authorize);
    }
}
