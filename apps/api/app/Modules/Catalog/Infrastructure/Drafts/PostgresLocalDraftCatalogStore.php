<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class PostgresLocalDraftCatalogStore implements LocalDraftCatalogStore
{
    public function create(DraftCatalogInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftCatalogOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Draft catalog transaction required.');
        }
        $operation = new DraftCatalogOperation((string) Str::ulid(), (string) Str::ulid(), (string) Str::ulid(), $input);
        $catalog = DB::table('catalogs')->insertGetId(['public_id' => $operation->catalogPublicId, 'merchant_public_id' => $input->merchantPublicId,
            'name' => $input->catalogName, 'status' => 'draft', 'version' => 1, 'deleted_at' => null]);
        $product = DB::table('products')->insertGetId(['public_id' => $operation->productPublicId, 'catalog_id' => $catalog,
            'name' => $input->productName, 'description' => $input->description, 'brand' => $input->brand,
            'product_type' => null, 'status' => 'draft', 'version' => 1, 'deleted_at' => null]);
        $snapshot = json_encode($operation->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('catalog_local_draft_operations')->insert(['public_id' => $operation->publicId, 'catalog_id' => $catalog, 'product_id' => $product,
            'merchant_public_id' => $input->merchantPublicId, 'actor_public_id' => $actorPublicId, 'schema_version' => 1,
            'key_hash' => $keyHash, 'request_hash' => $requestHash, 'response_hash' => hash('sha256', $snapshot),
            'correlation_id' => $correlationId, 'response_snapshot_ciphertext' => Crypt::encryptString($snapshot)]);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftCatalogOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $row = DB::table('catalog_local_draft_operations')->where('public_id', $operationPublicId)->where('actor_public_id', $actorPublicId)->first();
        if ($row === null) {
            throw new DraftCatalogFailure('not_found');
        }
        $plaintext = Crypt::decryptString($row->response_snapshot_ciphertext);
        if ((int) $row->schema_version !== 1 || ! hash_equals($row->response_hash, hash('sha256', $plaintext))) {
            throw new LogicException('Draft catalog snapshot unavailable.');
        }
        $snapshot = json_decode($plaintext, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($snapshot)) {
            throw new LogicException('Draft catalog snapshot unavailable.');
        }

        return DraftCatalogOperation::restore($row->public_id, $snapshot);
    }
}
