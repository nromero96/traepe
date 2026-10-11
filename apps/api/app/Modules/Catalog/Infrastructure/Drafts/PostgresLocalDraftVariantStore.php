<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftVariantStore;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCurrentProfile;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;
use App\Modules\Catalog\Domain\Drafts\DraftVariantData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantPage;
use App\Modules\Catalog\Domain\Drafts\DraftVariantProfile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

final class PostgresLocalDraftVariantStore implements LocalDraftVariantStore
{
    public function append(DraftVariantInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftVariantOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Draft variant transaction required.');
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
        $catalog = new DraftCatalogProfile($c->public_id, $c->merchant_public_id, $c->name, (int) $c->version);
        $product = new DraftProductProfile($p->public_id, $c->public_id, new DraftProductData($p->name, $p->description, $p->brand), (int) $p->version);
        if ($catalog->version !== $input->expectedVersion || $catalog->version === 2147483647 || $product->version === 2147483647) {
            throw new DraftCatalogFailure('version_conflict');
        }
        if (DB::table('product_variants')->where('merchant_public_id', $c->merchant_public_id)->where('sku', $input->variant->sku)->exists()) {
            throw new DraftCatalogFailure('sku_conflict');
        }
        $variant = new DraftVariantProfile((string) Str::ulid(), $c->public_id, $p->public_id, $input->variant);
        $operation = new DraftVariantOperation((string) Str::ulid(), $catalog, $product, $variant);
        $fields = $input->variant->snapshot();
        $fields['attributes'] = json_encode($fields['attributes'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        try {
            $id = DB::table('product_variants')->insertGetId(['public_id' => $variant->publicId, 'catalog_id' => $c->id, 'product_id' => $p->id,
                'merchant_public_id' => $c->merchant_public_id, ...$fields, 'status' => 'draft', 'version' => 1, 'deleted_at' => null]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505' && str_contains((string) ($exception->errorInfo[2] ?? ''), 'unique constraint "catalog_variants_merchant_sku_unique"')) {
                throw new DraftCatalogFailure('sku_conflict');
            }
            throw $exception;
        }
        if (DB::table('products')->where('id', $p->id)->where('version', $product->version)->update(['version' => $product->version + 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]) !== 1
            || DB::table('catalogs')->where('id', $c->id)->where('version', $catalog->version)->update(['version' => $catalog->version + 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]) !== 1) {
            throw new DraftCatalogFailure('version_conflict');
        }
        $plain = json_encode($operation->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('catalog_local_draft_variant_operations')->insert(['public_id' => $operation->publicId, 'catalog_id' => $c->id, 'product_id' => $p->id, 'variant_id' => $id,
            'merchant_public_id' => $catalog->merchantPublicId, 'actor_public_id' => $actorPublicId, 'schema_version' => 1,
            'catalog_version_before' => $catalog->version, 'catalog_version_after' => $catalog->version + 1, 'product_version_before' => $product->version, 'product_version_after' => $product->version + 1,
            'key_hash' => $keyHash, 'request_hash' => $requestHash, 'response_hash' => hash('sha256', $plain), 'correlation_id' => $correlationId, 'response_snapshot_ciphertext' => Crypt::encryptString($plain)]);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftVariantOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $row = DB::table('catalog_local_draft_variant_operations')->where('public_id', $operationPublicId)->where('actor_public_id', $actorPublicId)->first();
        if ($row === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $plain = Crypt::decryptString($row->response_snapshot_ciphertext);
        if ((int) $row->schema_version !== 1 || ! hash_equals($row->response_hash, hash('sha256', $plain))) {
            throw new LogicException('Draft variant snapshot unavailable.');
        }
        $value = json_decode($plain, false, 32, JSON_THROW_ON_ERROR);
        if (! $value instanceof stdClass) {
            throw new LogicException('Draft variant snapshot unavailable.');
        }
        $operation = DraftVariantOperation::restore($row->public_id, (array) $value);
        if ($operation->catalogBefore->merchantPublicId !== $row->merchant_public_id || $operation->catalogBefore->version !== (int) $row->catalog_version_before
            || $operation->catalogBefore->version + 1 !== (int) $row->catalog_version_after || $operation->productBefore->version !== (int) $row->product_version_before
            || $operation->productBefore->version + 1 !== (int) $row->product_version_after) {
            throw new LogicException('Draft variant snapshot unavailable.');
        }

        return $operation;
    }

    public function page(string $catalogPublicId, string $productPublicId, string $actorPublicId, int $limit, ?DraftVariantCursor $cursor): DraftVariantPage
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $rows = DB::select(<<<'SQL'
            SELECT c.version AS catalog_version,p.version AS product_version,v.id,v.public_id,v.name,v.sku,v.size,v.unit,v.barcode,v.attributes,v.version
            FROM catalogs c
            JOIN catalog_local_draft_operations o ON o.catalog_id=c.id AND o.merchant_public_id=c.merchant_public_id
            JOIN products p ON p.catalog_id=c.id
            LEFT JOIN LATERAL (
                SELECT v.id,v.public_id,v.name,v.sku,v.size,v.unit,v.barcode,v.attributes,v.version FROM product_variants v
                JOIN catalog_local_draft_variant_operations j ON j.variant_id=v.id AND j.product_id=p.id AND j.catalog_id=c.id
                    AND j.merchant_public_id=c.merchant_public_id AND j.actor_public_id=o.actor_public_id
                WHERE v.product_id=p.id AND v.catalog_id=c.id AND v.merchant_public_id=c.merchant_public_id AND v.status='draft' AND v.deleted_at IS NULL AND v.id>?
                  AND (?::integer IS NULL OR (c.version=?::integer AND p.version=?::integer))
                ORDER BY v.id ASC LIMIT ?
            ) v ON true
            WHERE c.public_id=? AND p.public_id=? AND o.actor_public_id=? AND c.status='draft' AND c.deleted_at IS NULL
              AND p.status='draft' AND p.deleted_at IS NULL AND p.product_type IS NULL
              AND (p.id=o.product_id OR EXISTS (
                SELECT 1 FROM catalog_local_draft_product_operations a WHERE a.product_id=p.id AND a.catalog_id=c.id
                  AND a.merchant_public_id=c.merchant_public_id AND a.actor_public_id=o.actor_public_id))
            ORDER BY v.id ASC
            SQL, [$cursor->lastId ?? 0, $cursor?->catalogVersion, $cursor?->catalogVersion, $cursor?->productVersion, $limit + 1, $catalogPublicId, $productPublicId, $actorPublicId]);
        if ($rows === []) {
            throw new DraftCatalogFailure('not_found');
        }
        $catalogVersion = (int) $rows[0]->catalog_version;
        $productVersion = (int) $rows[0]->product_version;
        if ($cursor !== null && ($cursor->catalogVersion !== $catalogVersion || $cursor->productVersion !== $productVersion)) {
            throw new DraftCatalogFailure('catalog_changed');
        }
        $valid = array_values(array_filter($rows, fn ($row) => $row->id !== null));
        $hasMore = count($valid) > $limit;
        $slice = array_slice($valid, 0, $limit);
        $variants = array_map(fn ($row) => new DraftVariantCurrentProfile($row->public_id, $catalogPublicId, $productPublicId,
            DraftVariantData::fromArray(['name' => $row->name, 'sku' => $row->sku, 'size' => $row->size, 'unit' => $row->unit, 'barcode' => $row->barcode,
                'attributes' => json_decode($row->attributes, false, 16, JSON_THROW_ON_ERROR)]), (int) $row->version), $slice);

        return new DraftVariantPage($catalogVersion, $productVersion, $variants, $hasMore, $slice === [] ? 0 : (int) $slice[array_key_last($slice)]->id);
    }
}
