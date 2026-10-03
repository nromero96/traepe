<?php

namespace App\Shared\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }
        $details = [];
        if ($exception instanceof ValidationException) {
            $status = 422;
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $details[] = ['field' => $field, 'code' => 'invalid', 'message' => $message];
                }
            }
        } else {
            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => $exception->status() ?? 403,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
        }
        [$code, $message] = match ($status) {
            400 => ['bad_request', 'Solicitud inválida.'],
            401 => ['unauthenticated', 'Autenticación requerida.'],
            403 => ['forbidden', 'Acceso denegado.'],
            404 => ['not_found', 'Recurso no encontrado.'],
            405 => ['method_not_allowed', 'Método no permitido.'],
            419 => ['csrf_token_mismatch', 'La sesión debe renovarse.'],
            422 => ['validation_failed', 'La entrada no es válida.'],
            429 => ['rate_limited', 'Demasiadas solicitudes.'],
            503 => ['service_unavailable', 'Servicio temporalmente no disponible.'],
            default => ['internal_error', 'Error interno.'],
        };
        $headers = $exception instanceof HttpExceptionInterface
            ? array_intersect_key($exception->getHeaders(), array_flip(['Allow', 'Retry-After'])) : [];
        $headers['X-Correlation-ID'] = $request->attributes->get('correlation_id');

        return ApiResponse::error($request, $code, $message, $status, $details, $headers);
    }
}
