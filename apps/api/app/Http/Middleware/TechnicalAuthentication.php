<?php

namespace App\Http\Middleware;

use App\Infrastructure\TechnicalAccess;
use App\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TechnicalAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(TechnicalAccess::class)->allows($request)) {
            return ApiResponse::error($request, 'technical_access_denied', 'Acceso técnico denegado.', 401, [], [
                'WWW-Authenticate' => 'Basic realm="traepe-local"',
            ]);
        }

        return $next($request);
    }
}
