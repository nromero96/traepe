<?php

namespace App\Modules\Marketplace\Infrastructure\Fixtures;

use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureStore;
use App\Modules\Marketplace\Domain\Fixtures\FixtureFailure;
use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalZoneSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class PostgresLocalDraftFixtureStore implements LocalDraftFixtureStore
{
    public function create(FixtureProfile $profile, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): FixtureOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Fixture transaction required.');
        }
        DB::table('countries')->insertOrIgnore(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $country = DB::table('countries')->where('code', 'ZZ')->lockForUpdate()->first();
        if ($country === null || $country->name !== 'Synthetic Country' || $country->currency_code !== 'ZZZ') {
            throw new FixtureFailure('fixture_country_conflict');
        }
        $marketPublicId = (string) Str::ulid();
        $marketId = DB::table('markets')->insertGetId(['public_id' => $marketPublicId, 'country_id' => $country->id,
            'name' => $profile->marketName(), 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']);
        $zoneIds = [];
        foreach (PostgisLocalZoneSource::FIXTURES as $index => [$inlineId, $priority, $wkt]) {
            $zoneIds[] = $id = (string) Str::ulid();
            DB::insert('INSERT INTO service_zones (public_id, market_id, name, priority, polygon) VALUES (?, ?, ?, ?, ST_Multi(ST_GeomFromText(?, 4326))::geography)',
                [$id, $marketId, 'Synthetic Zone '.['A', 'B', 'C'][$index], $priority, $wkt]);
        }
        $operation = new FixtureOperation((string) Str::ulid(), $profile, $country->public_id, $marketPublicId, $zoneIds);
        DB::table('marketplace_local_fixture_operations')->insert(['public_id' => $operation->publicId, 'market_id' => $marketId,
            'actor_public_id' => $actorPublicId, 'profile_version' => $profile->value, 'schema_version' => 1,
            'key_hash' => $keyHash, 'request_hash' => $requestHash, 'correlation_id' => $correlationId,
            'response_snapshot' => json_encode($operation->snapshot(), JSON_THROW_ON_ERROR)]);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): FixtureOperation
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $row = DB::table('marketplace_local_fixture_operations')->where('public_id', $operationPublicId)->where('actor_public_id', $actorPublicId)->first();
        if ($row === null) {
            throw new LogicException('Fixture operation unavailable.');
        }
        $snapshot = json_decode($row->response_snapshot, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($snapshot) || ($snapshot['profile_version'] ?? null) !== $row->profile_version || (int) $row->schema_version !== 1) {
            throw new LogicException('Invalid fixture snapshot.');
        }

        return FixtureOperation::restore($row->public_id, $snapshot);
    }
}
