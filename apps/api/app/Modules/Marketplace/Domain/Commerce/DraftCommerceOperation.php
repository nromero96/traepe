<?php

namespace App\Modules\Marketplace\Domain\Commerce;

use InvalidArgumentException;

final readonly class DraftCommerceOperation
{
    public function __construct(
        public string $publicId,
        public MerchantPublicId $merchant,
        public BranchPublicId $branch,
        public DraftCommerceInput $input,
    ) {
        if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $publicId)) {
            throw new InvalidArgumentException('Invalid draft commerce operation.');
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return ['schema_version' => 1,
            'merchant' => ['public_id' => $this->merchant->value, 'legal_name' => $this->input->legalName,
                'trade_name' => $this->input->tradeName, 'status' => 'draft', 'version' => 1],
            'branch' => ['public_id' => $this->branch->value, 'market_public_id' => $this->input->market->value,
                'name' => $this->input->branchName, 'longitude' => $this->input->point->longitude, 'latitude' => $this->input->point->latitude,
                'timezone' => $this->input->timezone, 'status' => 'draft', 'version' => 1]];
    }

    /** @param array<array-key, mixed> $snapshot */
    public static function restore(string $publicId, array $snapshot): self
    {
        if (! DraftCommerceInput::keys($snapshot, ['branch', 'merchant', 'schema_version']) || $snapshot['schema_version'] !== 1
            || ! is_array($snapshot['merchant']) || ! is_array($snapshot['branch'])) {
            throw new InvalidArgumentException('Invalid draft commerce snapshot.');
        }
        $merchant = $snapshot['merchant'];
        $branch = $snapshot['branch'];
        if (! DraftCommerceInput::keys($merchant, ['legal_name', 'public_id', 'status', 'trade_name', 'version'])
            || ! DraftCommerceInput::keys($branch, ['latitude', 'longitude', 'market_public_id', 'name', 'public_id', 'status', 'timezone', 'version'])
            || ! is_string($merchant['public_id']) || ! is_string($branch['public_id'])
            || $merchant['status'] !== 'draft' || $branch['status'] !== 'draft' || $merchant['version'] !== 1 || $branch['version'] !== 1) {
            throw new InvalidArgumentException('Invalid draft commerce snapshot.');
        }
        $merchantPublicId = $merchant['public_id'];
        $branchPublicId = $branch['public_id'];
        unset($merchant['public_id'], $merchant['status'], $merchant['version'], $branch['public_id'], $branch['status'], $branch['version']);
        $input = DraftCommerceInput::fromArray(['merchant' => $merchant, 'branch' => $branch]);

        return new self($publicId, new MerchantPublicId($merchantPublicId), new BranchPublicId($branchPublicId), $input);
    }
}
