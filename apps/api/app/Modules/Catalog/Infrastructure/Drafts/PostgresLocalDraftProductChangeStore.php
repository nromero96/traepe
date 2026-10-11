<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeStore;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductProfile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class PostgresLocalDraftProductChangeStore implements LocalDraftProductChangeStore
{
    public function change(DraftProductChangeInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftProductChangeOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Draft product change transaction required.');
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
        $before = new DraftProductProfile($p->public_id, $c->public_id, new DraftProductData($p->name, $p->description, $p->brand), (int) $p->version);
        if ($catalog->version !== $input->expectedVersion || $catalog->version === 2147483647 || $before->version === 2147483647) {
            throw new DraftCatalogFailure('version_conflict');
        }
        if ($before->product->snapshot() === $input->product->snapshot()) {
            throw new DraftCatalogFailure('no_changes');
        }
        $operation = new DraftProductChangeOperation((string) Str::ulid(), $catalog, $before, new DraftProductProfile($p->public_id, $c->public_id, $input->product, $before->version + 1));
        if (DB::table('products')->where('id', $p->id)->where('version', $before->version)->update([...$input->product->snapshot(), 'version' => $before->version + 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]) !== 1
            || DB::table('catalogs')->where('id', $c->id)->where('version', $catalog->version)->update(['version' => $catalog->version + 1, 'updated_at' => DB::raw('CURRENT_TIMESTAMP')]) !== 1) {
            throw new DraftCatalogFailure('version_conflict');
        }
        $snapshot = json_encode($operation->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('catalog_local_draft_product_changes')->insert(['public_id' => $operation->publicId, 'catalog_id' => $c->id, 'product_id' => $p->id,
            'merchant_public_id' => $catalog->merchantPublicId, 'actor_public_id' => $actorPublicId, 'schema_version' => 1,
            'catalog_version_before' => $catalog->version, 'catalog_version_after' => $catalog->version + 1, 'product_version_before' => $before->version, 'product_version_after' => $before->version + 1,
            'key_hash' => $keyHash, 'request_hash' => $requestHash, 'response_hash' => hash('sha256', $snapshot), 'correlation_id' => $correlationId, 'response_snapshot_ciphertext' => Crypt::encryptString($snapshot)]);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftProductChangeOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $row = DB::table('catalog_local_draft_product_changes')->where('public_id', $operationPublicId)->where('actor_public_id', $actorPublicId)->first();
        if ($row === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $plain = Crypt::decryptString($row->response_snapshot_ciphertext);
        if ((int) $row->schema_version !== 1 || ! hash_equals($row->response_hash, hash('sha256', $plain))) {
            throw new LogicException('Draft product change snapshot unavailable.');
        }
        $value = json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new LogicException('Draft product change snapshot unavailable.');
        }
        $operation = DraftProductChangeOperation::restore($row->public_id, $value);
        if ($operation->catalogBefore->merchantPublicId !== $row->merchant_public_id || $operation->catalogBefore->version !== (int) $row->catalog_version_before
            || $operation->catalogBefore->version + 1 !== (int) $row->catalog_version_after || $operation->productBefore->version !== (int) $row->product_version_before
            || $operation->productAfter->version !== (int) $row->product_version_after) {
            throw new LogicException('Draft product change snapshot unavailable.');
        }

        return $operation;
    }
}
