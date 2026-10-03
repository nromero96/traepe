<?php

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiResponse
{
    /** @param array<string, mixed> $attributes */
    public static function resource(Request $request, string $type, string $id, array $attributes, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => ['type' => $type, 'id' => $id, 'attributes' => $attributes],
            'meta' => ['correlation_id' => $request->attributes->get('correlation_id')],
        ], $status);
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @param  array<string, mixed>  $filters
     */
    public static function collection(Request $request, array $data, ?string $nextCursor, bool $hasMore, array $filters = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'correlation_id' => $request->attributes->get('correlation_id'),
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
                'filters' => (object) $filters,
            ],
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $details
     * @param  array<string, string|list<string>>  $headers
     */
    public static function error(Request $request, string $code, string $message, int $status, array $details = [], array $headers = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'correlation_id' => $request->attributes->get('correlation_id'),
            ],
        ], $status, $headers);
    }
}
