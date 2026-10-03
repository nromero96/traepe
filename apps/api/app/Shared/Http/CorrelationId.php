<?php

namespace App\Shared\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Correlation-ID');
        $id = is_string($incoming) && preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $incoming) && Str::isUlid($incoming)
            ? $incoming : (string) Str::ulid();
        $request->attributes->set('correlation_id', $id);
        $start = hrtime(true);

        return Context::scope(function () use ($request, $next, $start, $id): Response {
            try {
                $response = $next($request);
                $response->headers->set('X-Correlation-ID', $id);
                Log::info('http.completed', ['status_code' => $response->getStatusCode(), 'duration_ms' => (int) ((hrtime(true) - $start) / 1_000_000)]);

                return $response;
            } catch (\Throwable $exception) {
                Log::error('http.failed', ['exception' => $exception]);
                throw $exception;
            }
        }, ['correlation_id' => $id]);
    }
}
