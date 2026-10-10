<?php

namespace App\Modules\Marketplace\Application\Fixtures;

use App\Modules\Marketplace\Domain\Fixtures\FixtureFailure;
use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CreateLocalDraftFixture
{
    public function __construct(private LocalDraftFixtureAccess $access, private LocalFixtureWriter $writer) {}

    public function execute(int $authenticatedId, FixtureProfile $profile, string $key, string $correlationId): FixtureOperation
    {
        $actor = $this->access->actor($authenticatedId, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if ($actor === null) {
            throw new FixtureFailure('forbidden');
        }
        $authorize = function () use ($authenticatedId, $actor): void {
            if ($this->access->actor($authenticatedId, new DateTimeImmutable('now', new DateTimeZone('UTC'))) !== $actor) {
                throw new FixtureFailure('forbidden');
            }
        };

        return $this->writer->execute($profile, $actor, $key, $correlationId, $authorize);
    }
}
