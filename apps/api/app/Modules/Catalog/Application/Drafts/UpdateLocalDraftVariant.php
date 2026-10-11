<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeOperation;
use DateTimeImmutable;
use DateTimeZone;

final readonly class UpdateLocalDraftVariant
{
    public function __construct(private LocalDraftVariantChangeAccess $access, private LocalDraftVariantChangeWriter $writer) {}

    public function execute(int $authenticatedId, DraftVariantChangeInput $input, string $key, string $correlationId): DraftVariantChangeOperation
    {
        $actor = $this->access->actor($authenticatedId, LocalDraftVariantChangeAccess::UPDATE, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }
        $authorize = function () use ($authenticatedId, $actor): void {
            if ($this->access->actor($authenticatedId, LocalDraftVariantChangeAccess::UPDATE, new DateTimeImmutable('now', new DateTimeZone('UTC'))) !== $actor) {
                throw new DraftCatalogFailure('forbidden');
            }
        };

        return $this->writer->execute($input, $actor, $key, $correlationId, $authorize);
    }
}
