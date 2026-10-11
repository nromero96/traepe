<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductWriter;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use App\Modules\Platform\Application\Delivery\IdempotencyStore;
use App\Modules\Platform\Domain\Delivery\IdempotencyExpired;
use App\Modules\Platform\Domain\Delivery\IdempotencyMismatch;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use Closure;
use DateTimeImmutable;
use DateTimeZone;

final readonly class PlatformLocalDraftProductWriter implements LocalDraftProductWriter
{
    public const SCOPE = 'catalog.local_draft_product.create.v1:'.LocalDraftProductAccess::SCOPE_PUBLIC_ID.':'.LocalDraftProductAccess::CREATE_RESOURCE;

    public function __construct(private IdempotencyStore $idempotency, private LocalDraftProductStore $store, private LocalDraftMerchantSource $merchants) {}

    public function execute(DraftProductInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftProductOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $payload = $input->fingerprintPayload();
        try {
            $reference = $this->idempotency->execute(self::SCOPE, 'identity.user:'.$actorPublicId, $key, $payload, new DateTimeImmutable('+24 hours', new DateTimeZone('UTC')),
                function () use ($input, $actorPublicId, $key, $payload, $correlationId, $authorize): string {
                    $authorize();
                    $profile = $this->store->profile($input->catalogPublicId, $actorPublicId);
                    if (! $this->merchants->lockOwnedDraft($profile->merchantPublicId, $actorPublicId)) {
                        throw new DraftCatalogFailure('not_found');
                    }

                    return $this->store->append($input, $actorPublicId, hash('sha256', $key), RequestFingerprint::hash($payload), $correlationId)->publicId;
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
