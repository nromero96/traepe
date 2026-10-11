<?php

namespace App\Modules\Catalog\Interfaces\Http;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftCatalog;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftCatalog;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Shared\Http\ApiResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;

final class LocalDraftCatalogController
{
    public function create(Request $request): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftCatalogAccess::CREATE);
        abort_unless($request->isJson(), 415);
        $key = $request->header('Idempotency-Key');
        try {
            $body = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($body) || ! is_string($key) || ! preg_match('/^[!-~]{1,255}$/D', $key)) {
                throw new InvalidArgumentException;
            }
            $input = DraftCatalogInput::fromArray($body);
        } catch (InvalidArgumentException|JsonException) {
            return ApiResponse::error($request, 'validation_failed', 'Invalid draft catalog request.', 422);
        }
        try {
            $operation = app(CreateLocalDraftCatalog::class)->execute($actor, $input, $key, (string) $request->attributes->get('correlation_id'));
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_catalog_operation', $operation->publicId, $operation->snapshot(), 201);
    }

    public function read(Request $request, string $operationPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftCatalogAccess::READ);
        if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $operationPublicId)) {
            return ApiResponse::error($request, 'validation_failed', 'Invalid draft catalog request.', 422);
        }
        try {
            $operation = app(ReadLocalDraftCatalog::class)->execute($actor, $operationPublicId);
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_catalog_operation', $operation->publicId, $operation->snapshot());
    }

    private function authorize(Request $request, string $capability): int
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $id = $request->user('web')?->getAuthIdentifier();
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 401);
        abort_if(app(LocalDraftCatalogAccess::class)->actor((int) $id, $capability, new DateTimeImmutable('now', new DateTimeZone('UTC'))) === null, 403);

        return (int) $id;
    }

    private function failure(Request $request, DraftCatalogFailure $failure): JsonResponse
    {
        $code = $failure->getMessage();
        $status = match ($code) {
            'forbidden' => 403,
            'not_found', 'merchant_not_available' => 404,
            'idempotency_mismatch', 'idempotency_expired' => 409,
            default => 500,
        };

        return ApiResponse::error($request, $status === 500 ? 'internal_error' : $code, 'Draft catalog operation rejected.', $status);
    }
}
