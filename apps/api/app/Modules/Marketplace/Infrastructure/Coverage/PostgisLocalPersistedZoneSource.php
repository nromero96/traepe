<?php

namespace App\Modules\Marketplace\Infrastructure\Coverage;

use App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PostgisLocalPersistedZoneSource implements LocalPersistedZoneSource
{
    public function matches(MarketPublicId $market, GeographicPoint $point): ?array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local persisted coverage diagnostic required.');
        }
        // One statement scopes drafts and keeps market/candidates in one snapshot.
        // DP-029: geography is evaluated natively; existing planar probes are separate.
        // Zero distance includes hole boundaries missed by ST_Covers in PostGIS 3.5.7.
        $rows = DB::select(<<<'SQL'
            WITH probe_point AS (
                SELECT ST_SetSRID(ST_MakePoint(?::double precision, ?::double precision), 4326)::geography AS location
            )
            SELECT zones.public_id, zones.priority
            FROM markets AS market
            CROSS JOIN probe_point
            LEFT JOIN service_zones AS zones
              ON zones.market_id = market.id
              AND zones.status = 'draft' AND zones.zone_type = 'fixture'
              AND (ST_Covers(zones.polygon, probe_point.location) OR ST_DWithin(zones.polygon, probe_point.location, 0))
            WHERE market.public_id = ? AND market.status = 'draft'
            SQL, [$point->longitude, $point->latitude, $market->value]);
        if ($rows === []) {
            return null;
        }
        $candidates = [];
        foreach ($rows as $row) {
            if ($row->public_id !== null) {
                $candidates[] = new ZoneCandidate($row->public_id, (int) $row->priority);
            }
        }

        return $candidates;
    }
}
