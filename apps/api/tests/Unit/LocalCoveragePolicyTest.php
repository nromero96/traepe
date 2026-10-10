<?php

namespace Tests\Unit;

use App\Modules\Marketplace\Application\Coverage\LocalCoverageProbe;
use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\LocalZoneSelectionPolicy;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalCoveragePolicyTest extends TestCase
{
    private const A = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private const B = '01ARZ3NDEKTSV4RRFFQ69G5FB0';

    private const C = '01ARZ3NDEKTSV4RRFFQ69G5FB1';

    public function test_valid_limits_preserve_longitude_and_latitude(): void
    {
        foreach ([[-180.0, -90.0], [180.0, 90.0], [0.0, 0.0], [6.0, 1.0]] as [$longitude, $latitude]) {
            $point = new GeographicPoint($longitude, $latitude);
            $this->assertSame($longitude, $point->longitude);
            $this->assertSame($latitude, $point->latitude);
        }
    }

    public static function invalidPoints(): array
    {
        return [[NAN, 0], [0, NAN], [INF, 0], [0, -INF], [-180.0001, 0], [180.0001, 0], [0, -90.0001], [0, 90.0001]];
    }

    #[DataProvider('invalidPoints')]
    public function test_invalid_points_are_rejected_without_normalization(float $longitude, float $latitude): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GeographicPoint($longitude, $latitude);
    }

    public function test_public_zone_reference_must_be_a_ulid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZoneCandidate('123', 10);
    }

    public function test_no_match_and_a_unique_maximum_do_not_depend_on_order(): void
    {
        $policy = new LocalZoneSelectionPolicy;
        $empty = $policy->select([]);
        $this->assertSame('outside', $empty->status);
        $this->assertNull($empty->zonePublicId);
        $candidates = [new ZoneCandidate(self::A, 10), new ZoneCandidate(self::B, 20), new ZoneCandidate(self::C, 5)];
        foreach ([$candidates, array_reverse($candidates)] as $order) {
            $result = $policy->select($order);
            $this->assertSame('selected', $result->status);
            $this->assertSame(self::B, $result->zonePublicId);
        }
    }

    public function test_a_maximum_tie_denies_and_a_higher_candidate_clears_a_lower_tie(): void
    {
        $policy = new LocalZoneSelectionPolicy;
        $tie = [new ZoneCandidate(self::A, 10), new ZoneCandidate(self::B, 10)];
        foreach ([$tie, array_reverse($tie)] as $order) {
            $result = $policy->select($order);
            $this->assertSame('ambiguous', $result->status);
            $this->assertNull($result->zonePublicId);
        }
        foreach ([[...$tie, new ZoneCandidate(self::C, 20)], [new ZoneCandidate(self::C, 20), ...$tie]] as $order) {
            $result = $policy->select($order);
            $this->assertSame('selected', $result->status);
            $this->assertSame(self::C, $result->zonePublicId);
        }
        $this->assertSame(self::A, $policy->select([new ZoneCandidate(self::A, 10), new ZoneCandidate(self::A, 10)])->zonePublicId);
    }

    public function test_application_evaluates_the_point_only_through_its_port(): void
    {
        $point = new GeographicPoint(0.5, 0.5);
        $source = $this->createMock(LocalZoneSource::class);
        $source->expects($this->once())->method('matches')->with($point)->willReturn([new ZoneCandidate(self::A, 10)]);
        $this->assertSame(self::A, (new LocalCoverageProbe($source))->evaluate($point)->zonePublicId);
    }
}
