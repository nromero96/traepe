<?php

namespace Tests\Unit;

use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixtureOperationTest extends TestCase
{
    private const IDS = ['01ARZ3NDEKTSV4RRFFQ69G5FAZ', '01ARZ3NDEKTSV4RRFFQ69G5FB0', '01ARZ3NDEKTSV4RRFFQ69G5FB1'];

    public function test_closed_profiles_and_snapshot_roundtrip(): void
    {
        foreach (FixtureProfile::cases() as $profile) {
            $operation = new FixtureOperation(self::IDS[0], $profile, self::IDS[1], self::IDS[2], self::IDS);
            $this->assertEquals($operation, FixtureOperation::restore($operation->publicId, array_reverse($operation->snapshot(), true)));
            $this->assertSame($profile === FixtureProfile::A ? 'Synthetic Market A' : 'Synthetic Market B', $profile->marketName());
        }
        $this->assertNull(FixtureProfile::tryFrom('synthetic-origin-a-v1 '));
        $this->assertNull(FixtureProfile::tryFrom('production'));
    }

    #[DataProvider('invalidSnapshots')]
    public function test_invalid_or_open_snapshots_are_rejected(array $changes): void
    {
        $operation = new FixtureOperation(self::IDS[0], FixtureProfile::A, self::IDS[1], self::IDS[2], self::IDS);
        $this->expectException(InvalidArgumentException::class);
        FixtureOperation::restore($operation->publicId, array_replace($operation->snapshot(), $changes));
    }

    public static function invalidSnapshots(): array
    {
        return [[['extra' => 'private']], [['country_public_id' => '11']], [['zone_public_ids' => []]], [['zone_public_ids' => [self::IDS[0], self::IDS[0], self::IDS[1]]]], [['zone_public_ids' => [1, 2, 3]]], [['profile_version' => null]]];
    }
}
