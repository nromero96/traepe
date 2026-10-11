<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CreateLocalDraftVariant
{
    public function __construct(private LocalDraftVariantAccess $access, private LocalDraftVariantWriter $writer) {}

    public function execute(int $authenticatedId, DraftVariantInput $input, string $key, string $correlationId): DraftVariantOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftVariantAccess::CREATE, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }
        $authorize = function () use ($authenticatedId, $actor): void {
            if ($this->access->actor($authenticatedId, LocalDraftVariantAccess::CREATE, new DateTimeImmutable('now', new DateTimeZone('UTC'))) !== $actor) {
                throw new DraftCatalogFailure('forbidden');
            }
        };

        return $this->writer->execute($input, $actor, $key, $correlationId, $authorize);
    }
}
