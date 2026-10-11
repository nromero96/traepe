<?php

namespace App\Modules\Marketplace\Infrastructure\Commerce;

use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceStore;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceWriter;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceFailure;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;
use App\Modules\Platform\Application\Delivery\IdempotencyStore;
use App\Modules\Platform\Domain\Delivery\IdempotencyExpired;
use App\Modules\Platform\Domain\Delivery\IdempotencyMismatch;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use Closure;
use DateTimeImmutable;
use DateTimeZone;

final readonly class PlatformLocalDraftCommerceWriter implements LocalDraftCommerceWriter
{
    public const SCOPE = 'marketplace.local_draft_commerce.create.v1:'.LocalDraftCommerceAccess::SCOPE_PUBLIC_ID.':'.LocalDraftCommerceAccess::CREATE_RESOURCE;

    public function __construct(private IdempotencyStore $idempotency, private LocalDraftCommerceStore $store) {}

    public function execute(DraftCommerceInput $input, string $actorPublicId, string $key, string $correlationId, Closure $authorize): DraftCommerceOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $payload = $input->fingerprintPayload();
        try {
            $reference = $this->idempotency->execute(self::SCOPE, 'identity.user:'.$actorPublicId, $key, $payload,
                new DateTimeImmutable('+24 hours', new DateTimeZone('UTC')),
                function () use ($input, $actorPublicId, $key, $payload, $correlationId, $authorize): string {
                    $authorize();

                    return $this->store->create($input, $actorPublicId, hash('sha256', $key), RequestFingerprint::hash($payload), $correlationId)->publicId;
                });
        } catch (IdempotencyExpired) {
            throw new DraftCommerceFailure('idempotency_expired');
        } catch (IdempotencyMismatch) {
            throw new DraftCommerceFailure('idempotency_mismatch');
        }
        $authorize();

        return $this->store->find($reference, $actorPublicId);
    }
}
