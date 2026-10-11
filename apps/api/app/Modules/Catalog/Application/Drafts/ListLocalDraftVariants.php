<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;
use App\Modules\Catalog\Domain\Drafts\DraftVariantPage;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ListLocalDraftVariants
{
    public function __construct(private LocalDraftVariantAccess $access, private LocalDraftVariantStore $store, private LocalDraftVariantCursorCodec $codec) {}

    public function execute(int $authenticatedId, string $catalogPublicId, string $productPublicId, int $limit, ?string $token): DraftVariantPage
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $actor = $this->access->actor($authenticatedId, LocalDraftVariantAccess::CONTENT, $now);
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }
        $cursor = $token === null ? null : $this->codec->decode($token);
        if ($cursor !== null) {
            if ($cursor->actorPublicId !== $actor || $cursor->catalogPublicId !== $catalogPublicId || $cursor->productPublicId !== $productPublicId || $cursor->limit !== $limit) {
                throw new DraftCatalogFailure('validation_failed');
            }
            if ($now->getTimestamp() >= $cursor->expiresAt) {
                throw new DraftCatalogFailure('cursor_expired');
            }
        }
        $page = $this->store->page($catalogPublicId, $productPublicId, $actor, $limit, $cursor);
        $next = $page->hasMore ? $this->codec->encode(new DraftVariantCursor($actor, $catalogPublicId, $productPublicId, $page->catalogVersion, $page->productVersion, $limit, $page->lastId, $cursor->expiresAt ?? $now->getTimestamp() + 900)) : null;

        return new DraftVariantPage($page->catalogVersion, $page->productVersion, $page->variants, $page->hasMore, $page->lastId, $next);
    }
}
