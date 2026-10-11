<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeStore;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCurrentProfile;
use App\Modules\Catalog\Domain\Drafts\DraftVariantData;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

final class PostgresLocalDraftVariantChangeStore implements LocalDraftVariantChangeStore
{
    public function change(DraftVariantChangeInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftVariantChangeOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Draft variant change transaction required.');
        }
        $c = DB::table('catalogs as c')->join('catalog_local_draft_operations as o', function ($join): void {
            $join->on('o.catalog_id', '=', 'c.id')->on('o.merchant_public_id', '=', 'c.merchant_public_id');
        })->where('c.public_id', $input->catalogPublicId)->where('o.actor_public_id', $actorPublicId)->where('c.status', 'draft')->whereNull('c.deleted_at')
            ->select('c.id', 'c.public_id', 'c.merchant_public_id', 'c.name', 'c.version', 'o.product_id as initial_product_id')->lock('FOR UPDATE OF c')->first();
        if ($c === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $p = DB::table('products as p')->where('p.public_id', $input->productPublicId)->where('p.catalog_id', $c->id)->where('p.status', 'draft')
            ->whereNull('p.deleted_at')->whereNull('p.product_type')->where(function ($query) use ($c, $actorPublicId): void {
                $query->where('p.id', $c->initial_product_id)->orWhereExists(function ($origin) use ($c, $actorPublicId): void {
                    $origin->selectRaw('1')->from('catalog_local_draft_product_operations as a')->whereColumn('a.product_id', 'p.id')
                        ->where('a.catalog_id', $c->id)->where('a.merchant_public_id', $c->merchant_public_id)->where('a.actor_public_id', $actorPublicId);
                });
            })->select('p.id', 'p.public_id', 'p.name', 'p.description', 'p.brand', 'p.version')->lock('FOR UPDATE OF p')->first();
        if ($p === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $v = DB::table('product_variants as v')->join('catalog_local_draft_variant_operations as j', function ($join): void {
            $join->on('j.variant_id', '=', 'v.id')->on('j.product_id', '=', 'v.product_id')->on('j.catalog_id', '=', 'v.catalog_id')->on('j.merchant_public_id', '=', 'v.merchant_public_id');
        })->where('v.public_id', $input->variantPublicId)->where('v.catalog_id', $c->id)->where('v.product_id', $p->id)->where('v.merchant_public_id', $c->merchant_public_id)
            ->where('j.actor_public_id', $actorPublicId)->where('v.status', 'draft')->whereNull('v.deleted_at')
            ->select('v.id', 'v.public_id', 'v.name', 'v.sku', 'v.size', 'v.unit', 'v.barcode', 'v.attributes', 'v.version')->lock('FOR UPDATE OF v')->first();
        if ($v === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $catalog = new DraftCatalogProfile($c->public_id, $c->merchant_public_id, $c->name, (int) $c->version);
        $product = new DraftProductProfile($p->public_id, $c->public_id, new DraftProductData($p->name, $p->description, $p->brand), (int) $p->version);
        $variantBefore = new DraftVariantCurrentProfile($v->public_id, $c->public_id, $p->public_id,
            DraftVariantData::fromArray(['name' => $v->name, 'sku' => $v->sku, 'size' => $v->size, 'unit' => $v->unit, 'barcode' => $v->barcode,
                'attributes' => json_decode($v->attributes, false, 16, JSON_THROW_ON_ERROR)]), (int) $v->version);
        if ($catalog->version !== $input->expectedVersion || $catalog->version === 2147483647 || $product->version === 2147483647 || $variantBefore->version === 2147483647) {
            throw new DraftCatalogFailure('version_conflict');
        }
        $dataAfter = $input->variant->withSku($variantBefore->variant->sku);
        if ($variantBefore->variant->fingerprint() === $dataAfter->fingerprint()) {
            throw new DraftCatalogFailure('no_changes');
        }
        $operation = new DraftVariantChangeOperation((string) Str::ulid(), $catalog, $product, $variantBefore,
            new DraftVariantCurrentProfile($v->public_id, $c->public_id, $p->public_id, $dataAfter, $variantBefore->version + 1));
        $fields = $input->variant->snapshot();
        $fields['attributes'] = json_encode($fields['attributes'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (DB::table('product_variants')->where('id', $v->id)->where('version', $variantBefore->version)->update([...$fields, 'version' => $variantBefore->version + 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]) !== 1
            || DB::table('products')->where('id', $p->id)->where('version', $product->version)->update(['version' => $product->version + 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]) !== 1
            || DB::table('catalogs')->where('id', $c->id)->where('version', $catalog->version)->update(['version' => $catalog->version + 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]) !== 1) {
            throw new DraftCatalogFailure('version_conflict');
        }
        $plain = json_encode($operation->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('catalog_local_draft_variant_changes')->insert(['public_id' => $operation->publicId, 'catalog_id' => $c->id, 'product_id' => $p->id, 'variant_id' => $v->id,
            'merchant_public_id' => $catalog->merchantPublicId, 'actor_public_id' => $actorPublicId, 'schema_version' => 1,
            'catalog_version_before' => $catalog->version, 'catalog_version_after' => $catalog->version + 1, 'product_version_before' => $product->version, 'product_version_after' => $product->version + 1,
            'variant_version_before' => $variantBefore->version, 'variant_version_after' => $variantBefore->version + 1,
            'key_hash' => $keyHash, 'request_hash' => $requestHash, 'response_hash' => hash('sha256', $plain), 'correlation_id' => $correlationId, 'response_snapshot_ciphertext' => Crypt::encryptString($plain)]);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftVariantChangeOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $row = DB::table('catalog_local_draft_variant_changes')->where('public_id', $operationPublicId)->where('actor_public_id', $actorPublicId)->first();
        if ($row === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $plain = Crypt::decryptString($row->response_snapshot_ciphertext);
        if ((int) $row->schema_version !== 1 || ! hash_equals($row->response_hash, hash('sha256', $plain))) {
            throw new LogicException('Draft variant change snapshot unavailable.');
        }
        $value = json_decode($plain, false, 32, JSON_THROW_ON_ERROR);
        if (! $value instanceof stdClass) {
            throw new LogicException('Draft variant change snapshot unavailable.');
        }
        $operation = DraftVariantChangeOperation::restore($row->public_id, (array) $value);
        if ($operation->catalogBefore->merchantPublicId !== $row->merchant_public_id || $operation->catalogBefore->version !== (int) $row->catalog_version_before
            || $operation->catalogBefore->version + 1 !== (int) $row->catalog_version_after || $operation->productBefore->version !== (int) $row->product_version_before
            || $operation->productBefore->version + 1 !== (int) $row->product_version_after || $operation->variantBefore->version !== (int) $row->variant_version_before
            || $operation->variantAfter->version !== (int) $row->variant_version_after) {
            throw new LogicException('Draft variant change snapshot unavailable.');
        }

        return $operation;
    }
}
