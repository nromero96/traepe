<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftProductCursor;
use App\Modules\Catalog\Domain\Drafts\DraftProductPage;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ListLocalDraftProducts
{
    public function __construct(private LocalDraftProductAccess $access, private LocalDraftProductStore $store, private LocalDraftProductCursorCodec $codec) {}

    public function execute(int $authenticatedId, string $catalogPublicId, int $limit, ?string $token): DraftProductPage
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $actor = $this->access->actor($authenticatedId, LocalDraftProductAccess::CONTENT, $now);
        if ($actor === null) {
            throw new DraftCatalogFailure('forbidden');
        }
        $cursor = $token === null ? null : $this->codec->decode($token);
        if ($cursor !== null) {
            if ($cursor->actorPublicId !== $actor || $cursor->catalogPublicId !== $catalogPublicId || $cursor->limit !== $limit) {
                throw new DraftCatalogFailure('validation_failed');
            }
            if ($now->getTimestamp() >= $cursor->expiresAt) {
                throw new DraftCatalogFailure('cursor_expired');
            }
        }
        $page = $this->store->page($catalogPublicId, $actor, $limit, $cursor);
        $next = $page->hasMore ? $this->codec->encode(new DraftProductCursor($actor, $catalogPublicId, $page->catalogVersion, $limit, $page->lastId, $cursor->expiresAt ?? $now->getTimestamp() + 900)) : null;

        return new DraftProductPage($page->catalogVersion, $page->products, $page->hasMore, $page->lastId, $next);
    }
}
