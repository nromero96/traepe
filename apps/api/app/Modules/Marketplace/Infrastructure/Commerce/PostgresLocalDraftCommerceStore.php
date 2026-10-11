<?php

namespace App\Modules\Marketplace\Infrastructure\Commerce;

use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceStore;
use App\Modules\Marketplace\Domain\Commerce\BranchPublicId;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceFailure;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;
use App\Modules\Marketplace\Domain\Commerce\MerchantPublicId;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class PostgresLocalDraftCommerceStore implements LocalDraftCommerceStore
{
    public function create(DraftCommerceInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftCommerceOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Draft commerce transaction required.');
        }
        $market = DB::table('markets')->where('public_id', $input->market->value)->where('status', 'draft')->lockForUpdate()->first();
        if ($market === null) {
            throw new DraftCommerceFailure('market_not_available');
        }
        $merchantPublicId = new MerchantPublicId((string) Str::ulid());
        $branchPublicId = new BranchPublicId((string) Str::ulid());
        $merchant = DB::table('merchants')->insertGetId(['public_id' => $merchantPublicId->value, 'legal_name' => $input->legalName,
            'trade_name' => $input->tradeName, 'status' => 'draft', 'version' => 1, 'tax_id' => null, 'risk_level' => null]);
        $branch = DB::selectOne('INSERT INTO branches (public_id, merchant_id, market_id, name, timezone, status, version, point) VALUES (?, ?, ?, ?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?::double precision, ?::double precision), 4326)::geography) RETURNING id',
            [$branchPublicId->value, $merchant, $market->id, $input->branchName, $input->timezone, 'draft', 1, $input->point->longitude, $input->point->latitude]);
        if ($branch === null) {
            throw new LogicException('Draft branch unavailable.');
        }
        $operation = new DraftCommerceOperation((string) Str::ulid(), $merchantPublicId, $branchPublicId, $input);
        $snapshot = json_encode($operation->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('marketplace_local_commerce_operations')->insert(['public_id' => $operation->publicId, 'merchant_id' => $merchant,
            'branch_id' => $branch->id, 'market_id' => $market->id, 'actor_public_id' => $actorPublicId, 'schema_version' => 1,
            'key_hash' => $keyHash, 'request_hash' => $requestHash, 'response_hash' => hash('sha256', $snapshot),
            'correlation_id' => $correlationId, 'response_snapshot_ciphertext' => Crypt::encryptString($snapshot)]);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftCommerceOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $row = DB::table('marketplace_local_commerce_operations')->where('public_id', $operationPublicId)->where('actor_public_id', $actorPublicId)->first();
        if ($row === null) {
            throw new DraftCommerceFailure('not_found');
        }
        $plaintext = Crypt::decryptString($row->response_snapshot_ciphertext);
        if ((int) $row->schema_version !== 1 || ! hash_equals($row->response_hash, hash('sha256', $plaintext))) {
            throw new LogicException('Draft commerce snapshot unavailable.');
        }
        $snapshot = json_decode($plaintext, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($snapshot)) {
            throw new LogicException('Draft commerce snapshot unavailable.');
        }

        return DraftCommerceOperation::restore($row->public_id, $snapshot);
    }
}
