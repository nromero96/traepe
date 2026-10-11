<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
use App\Modules\Platform\Application\Delivery\IdempotencyStore;
use App\Modules\Platform\Domain\Delivery\IdempotencyExpired;
use App\Modules\Platform\Domain\Delivery\IdempotencyMismatch;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use Closure;
use DateTimeImmutable;
use DateTimeZone;

final readonly class PlatformLocalDraftCatalogWriter implements LocalDraftCatalogWriter
{
    public const SCOPE = 'catalog.local_draft_catalog.create.v1:'.LocalDraftCatalogAccess::SCOPE_PUBLIC_ID.':'.LocalDraftCatalogAccess::CREATE_RESOURCE;

    public function __construct(private IdempotencyStore $idempotency, private LocalDraftCatalogStore $store, private LocalDraftMerchantSource $merchants) {}

    public function execute(DraftCatalogInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftCatalogOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $payload = $input->fingerprintPayload();
        try {
            $reference = $this->idempotency->execute(self::SCOPE, 'identity.user:'.$actorPublicId, $key, $payload,
                new DateTimeImmutable('+24 hours', new DateTimeZone('UTC')),
                function () use ($input, $actorPublicId, $key, $payload, $correlationId, $authorize): string {
                    $authorize();
                    if (! $this->merchants->lockOwnedDraft($input->merchantPublicId, $actorPublicId)) {
                        throw new DraftCatalogFailure('merchant_not_available');
                    }

                    return $this->store->create($input, $actorPublicId, hash('sha256', $key), RequestFingerprint::hash($payload), $correlationId)->publicId;
                });
        } catch (IdempotencyExpired) {
            throw new DraftCatalogFailure('idempotency_expired');
        } catch (IdempotencyMismatch) {
            throw new DraftCatalogFailure('idempotency_mismatch');
        }
        $authorize();

        return $this->store->find($reference, $actorPublicId);
    }
}
