<?php

namespace Tests\Unit;

use App\Modules\Marketplace\Application\Commerce\CreateLocalDraftCommerce;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceStore;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceWriter;
use App\Modules\Marketplace\Application\Commerce\ReadLocalDraftCommerce;
use App\Modules\Marketplace\Domain\Commerce\BranchPublicId;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceFailure;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;
use App\Modules\Marketplace\Domain\Commerce\MerchantPublicId;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DraftCommerceTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private function body(): array
    {
        return ['merchant' => ['legal_name' => ' Synthetic Merchant ', 'trade_name' => 'Synthetic Commerce'],
            'branch' => ['market_public_id' => self::ID, 'name' => 'Synthetic Branch', 'longitude' => 0.5, 'latitude' => 1.5, 'timezone' => 'Etc/UTC']];
    }

    private function operation(): DraftCommerceOperation
    {
        return new DraftCommerceOperation(self::ID, new MerchantPublicId(self::ID), new BranchPublicId(self::ID), DraftCommerceInput::fromArray($this->body()));
    }

    public function test_input_preserves_names_unicode_bounds_point_and_immutable_state(): void
    {
        $body = $this->body();
        $body['merchant']['trade_name'] = str_repeat('á', 255);
        $body['branch']['longitude'] = -180;
        $body['branch']['latitude'] = 90;
        $input = DraftCommerceInput::fromArray($body);
        $this->assertSame($body['merchant']['legal_name'], $input->legalName);
        $this->assertSame(255, mb_strlen($input->tradeName));
        $this->assertSame(-180.0, $input->point->longitude);
        $this->assertSame(90.0, $input->point->latitude);
        try {
            $input->legalName = 'changed';
            $this->fail('Draft input mutation accepted.');
        } catch (Error) {
            $this->assertSame($body['merchant']['legal_name'], $input->legalName);
        }
    }

    public function test_invalid_or_open_shapes_names_coordinates_and_timezone_are_rejected(): void
    {
        $invalid = [[], $this->body() + ['actor' => self::ID]];
        foreach (['merchant' => ['legal_name', 'trade_name'], 'branch' => ['name']] as $object => $fields) {
            foreach ($fields as $field) {
                foreach (['', ' ', "Synthetic\nCanary", "Synthetic\0Canary", str_repeat('á', 256), null, 1] as $value) {
                    $body = $this->body();
                    $body[$object][$field] = $value;
                    $invalid[] = $body;
                }
            }
        }
        foreach (['longitude' => [true, '0.5', null, INF, NAN, -180.001, 180.001], 'latitude' => [false, '1', -90.001, 90.001],
            'market_public_id' => ['1', strtolower(self::ID)], 'timezone' => ['UTC', 'Etc/UTC ', 'America/Lima']] as $field => $values) {
            foreach ($values as $value) {
                $body = $this->body();
                $body['branch'][$field] = $value;
                $invalid[] = $body;
            }
        }
        foreach (['merchant', 'branch'] as $object) {
            $body = $this->body();
            $body[$object]['status'] = 'active';
            $invalid[] = $body;
            foreach (array_keys($this->body()[$object]) as $field) {
                $body = $this->body();
                unset($body[$object][$field]);
                $invalid[] = $body;
            }
        }
        foreach ($invalid as $body) {
            try {
                DraftCommerceInput::fromArray($body);
                $this->fail('Invalid draft input accepted.');
            } catch (InvalidArgumentException $failure) {
                $this->assertStringNotContainsString('Canary', $failure->getMessage());
            }
        }
    }

    public function test_fingerprint_equates_numeric_forms_and_signed_zero_without_accepting_floats_in_platform(): void
    {
        $a = $b = $this->body();
        $a['branch']['longitude'] = 1;
        $b['branch']['longitude'] = 1.0;
        $a['branch']['latitude'] = 0;
        $b['branch']['latitude'] = -0.0;
        $hash = fn ($body) => RequestFingerprint::hash(DraftCommerceInput::fromArray($body)->fingerprintPayload());
        $this->assertSame($hash($a), $hash($b));
        $b['merchant']['legal_name'] .= ' ';
        $this->assertNotSame($hash($a), $hash($b));
    }

    public function test_snapshot_roundtrip_is_closed_and_rejects_unknown_state_or_shape(): void
    {
        $operation = $this->operation();
        $this->assertSame($operation->snapshot(), DraftCommerceOperation::restore(self::ID, $operation->snapshot())->snapshot());
        $invalid = [$operation->snapshot() + ['actor' => self::ID]];
        foreach (['merchant', 'branch'] as $object) {
            foreach (['status' => 'active', 'version' => 2, 'public_id' => '1', 'extra' => true] as $field => $value) {
                $snapshot = $operation->snapshot();
                $snapshot[$object][$field] = $value;
                $invalid[] = $snapshot;
            }
        }
        foreach ($invalid as $snapshot) {
            try {
                DraftCommerceOperation::restore(self::ID, $snapshot);
                $this->fail('Invalid snapshot accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cases_require_independent_access_and_callback_revalidation(): void
    {
        $access = $this->createMock(LocalDraftCommerceAccess::class);
        $writer = $this->createMock(LocalDraftCommerceWriter::class);
        $access->expects($this->exactly(2))->method('actor')->with(11, LocalDraftCommerceAccess::CREATE)->willReturnOnConsecutiveCalls(self::ID, null);
        $writer->expects($this->once())->method('execute')->willReturnCallback(function ($input, $actor, $key, $correlation, $authorize) {
            $authorize();

            return $this->operation();
        });
        try {
            (new CreateLocalDraftCommerce($access, $writer))->execute(11, $this->operation()->input, 'key', self::ID);
            $this->fail('Revoked create accepted.');
        } catch (DraftCommerceFailure $failure) {
            $this->assertSame('forbidden', $failure->getMessage());
        }
        $read = $this->createMock(LocalDraftCommerceAccess::class);
        $store = $this->createMock(LocalDraftCommerceStore::class);
        $read->expects($this->once())->method('actor')->with(11, LocalDraftCommerceAccess::READ)->willReturn(null);
        $store->expects($this->never())->method('find');
        $this->expectException(DraftCommerceFailure::class);
        (new ReadLocalDraftCommerce($read, $store))->execute(11, self::ID);
    }
}
