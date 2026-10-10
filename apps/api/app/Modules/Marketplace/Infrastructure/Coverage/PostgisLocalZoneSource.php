<?php

namespace App\Modules\Marketplace\Infrastructure\Coverage;

use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PostgisLocalZoneSource implements LocalZoneSource
{
    // Synthetic coordinates and references; no real market or district is represented.
    public const FIXTURES = [
        ['01ARZ3NDEKTSV4RRFFQ69G5FAZ', 10, 'POLYGON((0 0,4 0,4 4,0 4,0 0),(1 1,1 2,2 2,2 1,1 1))'],
        ['01ARZ3NDEKTSV4RRFFQ69G5FB0', 20, 'POLYGON((3 0,6 0,6 2,3 2,3 0))'],
        ['01ARZ3NDEKTSV4RRFFQ69G5FB1', 20, 'POLYGON((5 1,7 1,7 3,5 3,5 1))'],
    ];

    public function matches(GeographicPoint $point): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local coverage fixture required.');
        }
        $bindings = [];
        foreach (self::FIXTURES as $fixture) {
            array_push($bindings, ...$fixture);
        }
        $bindings[] = $point->longitude;
        $bindings[] = $point->latitude;
        // Only inline fixtures are queried; no module's private tables are accessed.
        $rows = DB::select(<<<'SQL'
            WITH fixtures(public_id, priority, wkt) AS (
                VALUES (?::text, ?::integer, ?::text), (?::text, ?::integer, ?::text), (?::text, ?::integer, ?::text)
            )
            SELECT public_id, priority FROM fixtures
            WHERE ST_Covers(ST_GeomFromText(wkt, 4326), ST_SetSRID(ST_MakePoint(?::double precision, ?::double precision), 4326))
            SQL, $bindings);

        return array_values(array_map(fn ($row) => new ZoneCandidate($row->public_id, (int) $row->priority), $rows));
    }
}
