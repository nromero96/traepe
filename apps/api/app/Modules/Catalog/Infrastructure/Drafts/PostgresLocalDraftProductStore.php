<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductCursor;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductPage;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class PostgresLocalDraftProductStore implements LocalDraftProductStore
{
    private function owned(string $catalogPublicId, string $actorPublicId): Builder
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return DB::table('catalogs as c')->join('catalog_local_draft_operations as o', function ($join): void {
            $join->on('o.catalog_id', '=', 'c.id')->on('o.merchant_public_id', '=', 'c.merchant_public_id');
        })->where('c.public_id', $catalogPublicId)->where('o.actor_public_id', $actorPublicId)->where('c.status', 'draft')->whereNull('c.deleted_at');
    }

    private function profileRow(\stdClass $row): DraftCatalogProfile
    {
        return new DraftCatalogProfile($row->public_id, $row->merchant_public_id, $row->name, (int) $row->version);
    }

    public function profile(string $catalogPublicId, string $actorPublicId): DraftCatalogProfile
    {
        $row = $this->owned($catalogPublicId, $actorPublicId)->select('c.public_id', 'c.merchant_public_id', 'c.name', 'c.version')->first();
        if ($row === null) {
            throw new DraftCatalogFailure('not_found');
        }

        return $this->profileRow($row);
    }

    public function page(string $catalogPublicId, string $actorPublicId, int $limit, ?DraftProductCursor $cursor): DraftProductPage
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $rows = DB::select(<<<'SQL'
            SELECT c.version AS catalog_version,p.id,p.public_id,p.name,p.description,p.brand,p.version
            FROM catalogs c
            JOIN catalog_local_draft_operations o ON o.catalog_id=c.id AND o.merchant_public_id=c.merchant_public_id
            LEFT JOIN LATERAL (
                SELECT id,public_id,name,description,brand,version FROM products
                WHERE catalog_id=c.id AND status='draft' AND deleted_at IS NULL AND id>?
                  AND (?::integer IS NULL OR c.version=?::integer)
                ORDER BY id ASC LIMIT ?
            ) p ON true
            WHERE c.public_id=? AND o.actor_public_id=? AND c.status='draft' AND c.deleted_at IS NULL
            ORDER BY p.id ASC
            SQL, [$cursor->lastId ?? 0, $cursor?->catalogVersion, $cursor?->catalogVersion, $limit + 1, $catalogPublicId, $actorPublicId]);
        if ($rows === []) {
            throw new DraftCatalogFailure('not_found');
        }
        $version = (int) $rows[0]->catalog_version;
        if ($cursor !== null && $cursor->catalogVersion !== $version) {
            throw new DraftCatalogFailure('catalog_changed');
        }
        $valid = array_values(array_filter($rows, fn ($row) => $row->id !== null));
        $hasMore = count($valid) > $limit;
        $slice = array_slice($valid, 0, $limit);
        $products = array_map(fn ($row) => new DraftProductProfile($row->public_id, $catalogPublicId, new DraftProductData($row->name, $row->description, $row->brand), (int) $row->version), $slice);

        return new DraftProductPage($version, $products, $hasMore, $slice === [] ? 0 : (int) $slice[array_key_last($slice)]->id);
    }

    public function append(DraftProductInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftProductOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Draft product transaction required.');
        }
        $row = $this->owned($input->catalogPublicId, $actorPublicId)->select('c.id', 'c.public_id', 'c.merchant_public_id', 'c.name', 'c.version')->lock('FOR UPDATE OF c')->first();
        if ($row === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $before = $this->profileRow($row);
        if ($before->version !== $input->expectedVersion || $before->version === 2147483647) {
            throw new DraftCatalogFailure('version_conflict');
        }
        $operation = new DraftProductOperation((string) Str::ulid(), $before, new DraftProductProfile((string) Str::ulid(), $before->publicId, $input->product));
        $product = DB::table('products')->insertGetId(['public_id' => $operation->product->publicId, 'catalog_id' => $row->id, ...$input->product->snapshot(), 'product_type' => null, 'status' => 'draft', 'version' => 1, 'deleted_at' => null]);
        if (DB::table('catalogs')->where('id', $row->id)->where('version', $before->version)->update(['version' => $before->version + 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]) !== 1) {
            throw new DraftCatalogFailure('version_conflict');
        }
        $snapshot = json_encode($operation->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('catalog_local_draft_product_operations')->insert(['public_id' => $operation->publicId, 'catalog_id' => $row->id, 'product_id' => $product, 'merchant_public_id' => $before->merchantPublicId, 'actor_public_id' => $actorPublicId,
            'schema_version' => 1, 'catalog_version_before' => $before->version, 'catalog_version_after' => $before->version + 1, 'key_hash' => $keyHash, 'request_hash' => $requestHash, 'response_hash' => hash('sha256', $snapshot),
            'correlation_id' => $correlationId, 'response_snapshot_ciphertext' => Crypt::encryptString($snapshot)]);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftProductOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $row = DB::table('catalog_local_draft_product_operations')->where('public_id', $operationPublicId)->where('actor_public_id', $actorPublicId)->first();
        if ($row === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $plain = Crypt::decryptString($row->response_snapshot_ciphertext);
        if ((int) $row->schema_version !== 1 || ! hash_equals($row->response_hash, hash('sha256', $plain))) {
            throw new LogicException('Draft product snapshot unavailable.');
        }
        $value = json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new LogicException('Draft product snapshot unavailable.');
        }
        $operation = DraftProductOperation::restore($row->public_id, $value);
        if ($operation->catalogBefore->merchantPublicId !== $row->merchant_public_id || $operation->catalogBefore->version !== (int) $row->catalog_version_before
            || $operation->catalogBefore->version + 1 !== (int) $row->catalog_version_after) {
            throw new LogicException('Draft product snapshot unavailable.');
        }

        return $operation;
    }
}
