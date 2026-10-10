<?php

namespace App\Modules\Marketplace\Infrastructure\Fixtures;

use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureStore;
use App\Modules\Marketplace\Application\Fixtures\LocalFixtureWriter;
use App\Modules\Marketplace\Domain\Fixtures\FixtureFailure;
use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use App\Modules\Platform\Application\Delivery\IdempotencyStore;
use App\Modules\Platform\Domain\Delivery\IdempotencyExpired;
use App\Modules\Platform\Domain\Delivery\IdempotencyMismatch;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use Closure;
use DateTimeImmutable;
use DateTimeZone;

final readonly class PlatformLocalFixtureWriter implements LocalFixtureWriter
{
    public const SCOPE = 'marketplace.local_draft_fixture.create.v1:'.LocalDraftFixtureAccess::SCOPE_PUBLIC_ID.':'.LocalDraftFixtureAccess::RESOURCE_PUBLIC_ID;

    public function __construct(private IdempotencyStore $idempotency, private LocalDraftFixtureStore $store) {}

    public function execute(FixtureProfile $profile, string $actorPublicId, string $key, string $correlationId, Closure $authorize): FixtureOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $payload = ['fixture_profile' => $profile->value, 'schema_version' => 1];
        try {
            $reference = $this->idempotency->execute(self::SCOPE, 'identity.user:'.$actorPublicId, $key, $payload,
                new DateTimeImmutable('+24 hours', new DateTimeZone('UTC')),
                function () use ($profile, $actorPublicId, $key, $payload, $correlationId, $authorize): string {
                    $authorize();

                    return $this->store->create($profile, $actorPublicId, hash('sha256', $key), RequestFingerprint::hash($payload), $correlationId)->publicId;
                });
        } catch (IdempotencyExpired) {
            throw new FixtureFailure('idempotency_expired');
        } catch (IdempotencyMismatch) {
            throw new FixtureFailure('idempotency_mismatch');
        }
        // A replay also requires current authorization before resolving the immutable result.
        $authorize();

        return $this->store->find($reference, $actorPublicId);
    }
}
