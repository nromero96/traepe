<?php

namespace App\Modules\Platform\Interfaces\Http;

use App\Modules\Platform\Application\Health\Readiness;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReadinessController
{
    public function __invoke(Request $request, Readiness $readiness): JsonResponse
    {
        $result = $readiness->check();

        return ApiResponse::resource($request, 'health', 'ready', $result, $result['status'] === 'ready' ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
