<?php

namespace App\Modules\Identity\Interfaces\Http;

use App\Modules\Identity\Application\Authorization\LocalAuthorizationProbe;
use App\Shared\Http\ApiResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocalAuthorizationProbeController
{
    public function __invoke(Request $request, LocalAuthorizationProbe $probe): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 401);
        $decision = $probe->evaluate((int) $id, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        abort_unless($decision->allowed(), 403);

        return ApiResponse::resource($request, 'authorization_probes', LocalAuthorizationProbe::RESOURCE_PUBLIC_ID, ['allowed' => true]);
    }
}
