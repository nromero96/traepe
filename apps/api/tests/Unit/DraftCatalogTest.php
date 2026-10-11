<?php

namespace Tests\Unit;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftCatalog;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogWriter;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftCatalog;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DraftCatalogTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private function body(): array
    {
        return ['merchant_public_id' => self::ID, 'catalog' => ['name' => ' Synthetic Catalog '],
            'product' => ['name' => 'Synthetic Product', 'description' => null, 'brand' => null]];
    }

    private function operation(): DraftCatalogOperation
    {
        return new DraftCatalogOperation(self::ID, self::ID, self::ID, DraftCatalogInput::fromArray($this->body()));
    }

    public function test_unicode_bounds_nullable_fields_literal_text_and_exact_names(): void
    {
        $body = $this->body();
        $body['product'] = ['name' => str_repeat('á', 255), 'description' => str_repeat('é', 3999)."\n", 'brand' => str_repeat('ñ', 255)];
        $input = DraftCatalogInput::fromArray($body);
        $this->assertSame(' Synthetic Catalog ', $input->catalogName);
        $this->assertSame(255, mb_strlen($input->productName));
        $this->assertSame(4000, mb_strlen($input->description));
        $body['product']['description'] = '<b>literal</b>';
        $this->assertSame('<b>literal</b>', DraftCatalogInput::fromArray($body)->description);
        $this->assertNull($this->operation()->input->brand);
        $body['product']['description'] = '';
        $this->assertSame('', DraftCatalogInput::fromArray($body)->description);
        try {
            $input->catalogName = 'changed';
            $this->fail('Immutable input mutation accepted.');
        } catch (Error) {
            $this->assertSame(' Synthetic Catalog ', $input->catalogName);
        }
    }

    public function test_closed_input_rejects_invalid_types_ids_missing_fields_and_controls_without_echo(): void
    {
        $invalid = [[], $this->body() + ['actor' => self::ID]];
        foreach (['catalog', 'product'] as $object) {
            foreach (array_keys($this->body()[$object]) as $field) {
                $body = $this->body();
                unset($body[$object][$field]);
                $invalid[] = $body;
            }
            $body = $this->body();
            $body[$object]['status'] = 'active';
            $invalid[] = $body;
        }
        foreach ([['catalog', 'name'], ['product', 'name'], ['product', 'brand']] as [$object, $field]) {
            foreach (['', ' ', "Synthetic\nCanary", "Synthetic\0Canary", "Synthetic\x7fCanary", str_repeat('á', 256), "\xff", 1, false] as $value) {
                $body = $this->body();
                $body[$object][$field] = $value;
                $invalid[] = $body;
            }
        }
        foreach ([null, 1, strtolower(self::ID), self::ID."\n", '8'.substr(self::ID, 1)] as $id) {
            $body = $this->body();
            $body['merchant_public_id'] = $id;
            $invalid[] = $body;
        }
        foreach ([str_repeat('é', 4001), "\tCanary", "\rCanary", "\0Canary", "\x7fCanary", "\xff", 1] as $description) {
            $body = $this->body();
            $body['product']['description'] = $description;
            $invalid[] = $body;
        }
        foreach ($invalid as $body) {
            try {
                DraftCatalogInput::fromArray($body);
                $this->fail('Invalid draft catalog input accepted.');
            } catch (InvalidArgumentException $failure) {
                $this->assertStringNotContainsString('Canary', $failure->getMessage());
            }
        }
    }

    public function test_fingerprint_preserves_whitespace_and_distinguishes_null_from_empty_description(): void
    {
        $hash = fn ($body) => RequestFingerprint::hash(DraftCatalogInput::fromArray($body)->fingerprintPayload());
        $body = $this->body();
        $base = $hash($body);
        $this->assertSame($base, $hash(array_reverse($body, true)));
        $body['product']['description'] = '';
        $this->assertNotSame($base, $hash($body));
        $body = $this->body();
        $body['catalog']['name'] = trim($body['catalog']['name']);
        $this->assertNotSame($base, $hash($body));
    }

    public function test_snapshot_restore_is_closed_and_checks_product_catalog_relationship(): void
    {
        $operation = $this->operation();
        $this->assertEquals($operation, DraftCatalogOperation::restore(self::ID, $operation->snapshot()));
        $invalid = [$operation->snapshot() + ['actor' => self::ID]];
        foreach (['catalog', 'product'] as $object) {
            foreach (['status' => 'active', 'version' => 2, 'public_id' => '1', 'product_type' => 'unrestricted'] as $field => $value) {
                $snapshot = $operation->snapshot();
                $snapshot[$object][$field] = $value;
                $invalid[] = $snapshot;
            }
        }
        $snapshot = $operation->snapshot();
        $snapshot['product']['catalog_public_id'] = '01ARZ3NDEKTSV4RRFFQ69G5FB0';
        $invalid[] = $snapshot;
        foreach ($invalid as $snapshot) {
            try {
                DraftCatalogOperation::restore(self::ID, $snapshot);
                $this->fail('Invalid snapshot accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_create_callback_reauthorizes_and_read_has_independent_access(): void
    {
        $access = $this->createMock(LocalDraftCatalogAccess::class);
        $writer = $this->createMock(LocalDraftCatalogWriter::class);
        $access->expects($this->exactly(2))->method('actor')->with(11, LocalDraftCatalogAccess::CREATE)->willReturnOnConsecutiveCalls(self::ID, null);
        $writer->expects($this->once())->method('execute')->willReturnCallback(function ($input, $actor, $key, $correlation, $authorize) {
            $authorize();

            return $this->operation();
        });
        try {
            (new CreateLocalDraftCatalog($access, $writer))->execute(11, $this->operation()->input, 'key', self::ID);
            $this->fail('Revoked creation accepted.');
        } catch (DraftCatalogFailure $failure) {
            $this->assertSame('forbidden', $failure->getMessage());
        }
        $access = $this->createMock(LocalDraftCatalogAccess::class);
        $store = $this->createMock(LocalDraftCatalogStore::class);
        $access->expects($this->once())->method('actor')->with(11, LocalDraftCatalogAccess::READ)->willReturn(null);
        $store->expects($this->never())->method('find');
        $this->expectException(DraftCatalogFailure::class);
        (new ReadLocalDraftCatalog($access, $store))->execute(11, self::ID);
    }
}
