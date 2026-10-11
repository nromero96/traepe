<?php

namespace Tests\Unit;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductWriter;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductCursor;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Platform\Domain\Delivery\RequestFingerprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DraftProductTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private function body(): array
    {
        return ['product' => ['name' => ' Exact Synthetic ', 'description' => null, 'brand' => null]];
    }

    public function test_exact_nullable_text_etag_and_fingerprint(): void
    {
        $input = DraftProductInput::fromArray(self::ID, '"catalog:'.self::ID.':v1"', $this->body());
        $this->assertSame(' Exact Synthetic ', $input->product->name);
        $base = RequestFingerprint::hash($input->fingerprintPayload());
        $body = $this->body();
        $body['product']['description'] = '';
        $this->assertNotSame($base, RequestFingerprint::hash(DraftProductInput::fromArray(self::ID, '"catalog:'.self::ID.':v1"', $body)->fingerprintPayload()));
        $this->assertNotSame($base, RequestFingerprint::hash(DraftProductInput::fromArray(self::ID, '"catalog:'.self::ID.':v2"', $this->body())->fingerprintPayload()));
        $body = ['product' => ['brand' => null, 'name' => ' Exact Synthetic ', 'description' => null]];
        $this->assertSame($base, RequestFingerprint::hash(DraftProductInput::fromArray(self::ID, '"catalog:'.self::ID.':v1"', $body)->fingerprintPayload()));
        $data = new DraftProductData(str_repeat('á', 255), str_repeat('ñ', 3999)."\n", str_repeat('á', 255));
        $this->assertSame(255, mb_strlen($data->name));
    }

    public function test_closed_input_text_and_precondition_limits(): void
    {
        foreach (['*', 'W/"catalog:'.self::ID.':v1"', '"catalog:'.self::ID.':v01"', '"catalog:'.self::ID.':v0"', '"catalog:'.self::ID.':v2147483648"', '"catalog:01ARZ3NDEKTSV4RRFFQ69G5FB0:v1"', '"catalog:'.self::ID.':v1", "x"'] as $etag) {
            try {
                DraftProductInput::fromArray(self::ID, $etag, $this->body());
                $this->fail('Invalid precondition accepted');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        foreach ([['name', ' '], ['name', str_repeat('á', 256)], ['name', "\n"], ['brand', ''], ['brand', "\x7f"], ['description', "\t"], ['description', str_repeat('á', 4001)], ['name', "\xff"], ['description', 1], ['status', 'active']] as [$field,$value]) {
            $body = $this->body();
            $body['product'][$field] = $value;
            try {
                DraftProductInput::fromArray(self::ID, '"catalog:'.self::ID.':v1"', $body);
                $this->fail('Invalid product accepted');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        foreach ([[], ['product' => []], $this->body() + ['actor' => self::ID]] as $body) {
            try {
                DraftProductInput::fromArray(self::ID, '"catalog:'.self::ID.':v1"', $body);
                $this->fail('Open shape accepted');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_original_operation_closed_restore_and_relation_revision_rules(): void
    {
        $op = new DraftProductOperation(self::ID, new DraftCatalogProfile(self::ID, self::ID, 'Catalog', 2147483646), new DraftProductProfile(self::ID, self::ID, new DraftProductData('Product', null, null)));
        $this->assertSame(2147483647, $op->snapshot()['catalog']['version_after']);
        $this->assertEquals($op, DraftProductOperation::restore(self::ID, $op->snapshot()));
        foreach (['version_after' => 1, 'status' => 'active', 'extra' => true] as $field => $value) {
            $snapshot = $op->snapshot();
            $snapshot['catalog'][$field] = $value;
            try {
                DraftProductOperation::restore(self::ID, $snapshot);
                $this->fail('Invalid history accepted');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        new DraftProductOperation(self::ID, new DraftCatalogProfile(self::ID, self::ID, 'Catalog', 2147483647), $op->product);
    }

    public function test_cursor_closed_integer_contract(): void
    {
        $cursor = new DraftProductCursor(self::ID, self::ID, 1, 50, PHP_INT_MAX, 2000000000);
        $this->assertEquals($cursor, DraftProductCursor::restore($cursor->snapshot()));
        foreach (['schema_version' => 2, 'purpose' => 'other', 'last_id' => 1.0, 'limit' => 51, 'catalog_version' => 0, 'expires_at' => '2000000000', 'actor_public_id' => 'invalid', 'extra' => true] as $field => $value) {
            try {
                DraftProductCursor::restore(array_replace($cursor->snapshot(), [$field => $value]));
                $this->fail('Invalid cursor accepted');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_create_reauthorization_cannot_change_actor(): void
    {
        $access = $this->createMock(LocalDraftProductAccess::class);
        $writer = $this->createMock(LocalDraftProductWriter::class);
        $access->expects($this->exactly(2))->method('actor')->willReturnOnConsecutiveCalls(self::ID, null);
        $writer->expects($this->once())->method('execute')->willReturnCallback(function ($input, $actor, $key, $correlation, $authorize) {
            $authorize();
        });
        $this->expectException(DraftCatalogFailure::class);
        $this->expectExceptionMessage('forbidden');
        (new CreateLocalDraftProduct($access, $writer))->execute(11, new DraftProductInput(self::ID, 1, new DraftProductData('Product', null, null)), 'key', self::ID);
    }
}
