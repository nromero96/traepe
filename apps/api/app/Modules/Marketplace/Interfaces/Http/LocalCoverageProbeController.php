<?php

namespace App\Modules\Marketplace\Interfaces\Http;

use App\Modules\Marketplace\Application\Coverage\LocalCoverageProbe;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class LocalCoverageProbeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = Validator::make($request->query(), [
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
        ])->validate();
        $point = new GeographicPoint((float) $input['longitude'], (float) $input['latitude']);
        // Resolve only after the environment middleware and request validation.
        $selection = app(LocalCoverageProbe::class)->evaluate($point);

        return ApiResponse::resource($request, 'local_coverage_probe', LocalCoverageProbe::VERSION, [
            'status' => $selection->status,
            'zone_id' => $selection->zonePublicId,
        ]);
    }
}
