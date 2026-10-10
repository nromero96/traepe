<?php

namespace App\Modules\Marketplace\Interfaces\Http;

use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageProbe;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use App\Shared\Http\ApiResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class LocalPersistedCoverageProbeController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $id = $request->user('web')?->getAuthIdentifier();
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 401);
        abort_unless(app(LocalPersistedCoverageAccess::class)->allows((int) $id, new DateTimeImmutable('now', new DateTimeZone('UTC'))), 403);

        $input = Validator::make($request->query(), [
            'market_public_id' => ['required', 'string', 'regex:/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
        ])->validate();
        $market = new MarketPublicId($input['market_public_id']);
        $point = new GeographicPoint((float) $input['longitude'], (float) $input['latitude']);
        // Resolve coverage only after authorization and format validation.
        $selection = app(LocalPersistedCoverageProbe::class)->evaluate($market, $point);

        return ApiResponse::resource($request, 'local_persisted_coverage_probe', LocalPersistedCoverageProbe::VERSION, [
            'status' => $selection->status,
            'zone_id' => $selection->zonePublicId,
        ]);
    }
}
