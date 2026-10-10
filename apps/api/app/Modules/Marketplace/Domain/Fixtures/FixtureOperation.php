<?php

namespace App\Modules\Marketplace\Domain\Fixtures;

use InvalidArgumentException;

final readonly class FixtureOperation
{
    /** @var list<string> */
    public array $zonePublicIds;

    /** @param array<array-key, string> $zonePublicIds */
    public function __construct(
        public string $publicId,
        public FixtureProfile $profile,
        public string $countryPublicId,
        public string $marketPublicId,
        array $zonePublicIds,
    ) {
        if (count($zonePublicIds) !== 3 || ! array_is_list($zonePublicIds) || count(array_unique($zonePublicIds)) !== 3) {
            throw new InvalidArgumentException('Invalid fixture snapshot.');
        }
        foreach ([$publicId, $countryPublicId, $marketPublicId, ...$zonePublicIds] as $id) {
            if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $id)) {
                throw new InvalidArgumentException('Invalid fixture reference.');
            }
        }
        $this->zonePublicIds = $zonePublicIds;
    }

    /** @return array{profile_version: string, country_public_id: string, market_public_id: string, zone_public_ids: list<string>} */
    public function snapshot(): array
    {
        return ['profile_version' => $this->profile->value, 'country_public_id' => $this->countryPublicId,
            'market_public_id' => $this->marketPublicId, 'zone_public_ids' => $this->zonePublicIds];
    }

    /** @param array<array-key, mixed> $snapshot */
    public static function restore(string $publicId, array $snapshot): self
    {
        $keys = array_keys($snapshot);
        sort($keys);
        if ($keys !== ['country_public_id', 'market_public_id', 'profile_version', 'zone_public_ids']
            || ! is_string($snapshot['profile_version']) || ! is_string($snapshot['country_public_id'])
            || ! is_string($snapshot['market_public_id']) || ! is_array($snapshot['zone_public_ids'])) {
            throw new InvalidArgumentException('Invalid fixture snapshot.');
        }
        foreach ($snapshot['zone_public_ids'] as $id) {
            if (! is_string($id)) {
                throw new InvalidArgumentException('Invalid fixture reference.');
            }
        }

        return new self($publicId, FixtureProfile::from($snapshot['profile_version']), $snapshot['country_public_id'], $snapshot['market_public_id'], $snapshot['zone_public_ids']);
    }
}
