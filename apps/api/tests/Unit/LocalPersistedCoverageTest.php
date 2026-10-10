<?php

namespace Tests\Unit;

use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageProbe;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LocalPersistedCoverageTest extends TestCase
{
    private const MARKET = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    public function test_market_reference_preserves_a_valid_public_ulid(): void
    {
        $this->assertSame(self::MARKET, (new MarketPublicId(self::MARKET))->value);
    }

    public function test_market_reference_rejects_invalid_input_without_echoing_it(): void
    {
        foreach (['1', '', strtolower(self::MARKET), '81ARZ3NDEKTSV4RRFFQ69G5FAZ', self::MARKET."\n", '01ARZ3NDEKTSV4RRFFQ69G5FAI'] as $reference) {
            try {
                new MarketPublicId($reference);
                $this->fail('Invalid market reference accepted.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('Invalid public market reference.', $error->getMessage());
            }
        }
    }

    public function test_application_distinguishes_missing_market_and_reuses_selection_with_explicit_context(): void
    {
        $market = new MarketPublicId(self::MARKET);
        $point = new GeographicPoint(0.5, 0.5);
        $a = new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FB0', -10);
        $b = new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FB1', -5);
        $tie = new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FB2', -5);
        foreach ([[null, 'market_not_found', null], [[], 'outside', null], [[$b, $a], 'selected', $b->publicId], [[$a, $b, $tie], 'ambiguous', null]] as [$candidates, $status, $zone]) {
            $source = $this->createMock(LocalPersistedZoneSource::class);
            $source->expects($this->once())->method('matches')->with($market, $point)->willReturn($candidates);
            $result = (new LocalPersistedCoverageProbe($source))->evaluate($market, $point);
            $this->assertSame($status, $result->status);
            $this->assertSame($zone, $result->zonePublicId);
        }
        $this->assertSame('local-persisted-coverage-v1', LocalPersistedCoverageProbe::VERSION);
    }
}
