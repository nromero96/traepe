<?php

namespace App\Modules\Marketplace\Domain\Commerce;

use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use InvalidArgumentException;

final readonly class DraftCommerceInput
{
    public function __construct(
        public string $legalName,
        public string $tradeName,
        public MarketPublicId $market,
        public string $branchName,
        public GeographicPoint $point,
        public string $timezone,
    ) {
        foreach ([$legalName, $tradeName, $branchName] as $name) {
            if (! mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 255 || trim($name, ' ') === ''
                || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
                throw new InvalidArgumentException('Invalid draft commerce input.');
            }
        }
        if ($timezone !== 'Etc/UTC') {
            throw new InvalidArgumentException('Invalid draft commerce input.');
        }
    }

    /** @param array<array-key, mixed> $body */
    public static function fromArray(array $body): self
    {
        if (! self::keys($body, ['branch', 'merchant']) || ! is_array($body['merchant']) || ! is_array($body['branch'])) {
            throw new InvalidArgumentException('Invalid draft commerce input.');
        }
        $merchant = $body['merchant'];
        $branch = $body['branch'];
        if (! self::keys($merchant, ['legal_name', 'trade_name'])
            || ! self::keys($branch, ['latitude', 'longitude', 'market_public_id', 'name', 'timezone'])
            || ! is_string($merchant['legal_name']) || ! is_string($merchant['trade_name'])
            || ! is_string($branch['market_public_id']) || ! is_string($branch['name']) || ! is_string($branch['timezone'])
            || (! is_int($branch['longitude']) && ! is_float($branch['longitude']))
            || (! is_int($branch['latitude']) && ! is_float($branch['latitude']))) {
            throw new InvalidArgumentException('Invalid draft commerce input.');
        }

        return new self($merchant['legal_name'], $merchant['trade_name'], new MarketPublicId($branch['market_public_id']),
            $branch['name'], new GeographicPoint((float) $branch['longitude'], (float) $branch['latitude']), $branch['timezone']);
    }

    /** @param array<array-key, mixed> $value
     * @param  list<string>  $expected
     */
    public static function keys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys);

        return $keys === $expected;
    }

    /** @return array<string, mixed> */
    public function fingerprintPayload(): array
    {
        return ['schema_version' => 1, 'merchant' => ['legal_name' => $this->legalName, 'trade_name' => $this->tradeName],
            'branch' => ['market_public_id' => $this->market->value, 'name' => $this->branchName, 'timezone' => $this->timezone,
                'longitude' => json_encode($this->point->longitude == 0.0 ? 0.0 : $this->point->longitude, JSON_THROW_ON_ERROR),
                'latitude' => json_encode($this->point->latitude == 0.0 ? 0.0 : $this->point->latitude, JSON_THROW_ON_ERROR)]];
    }
}
