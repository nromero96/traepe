<?php

namespace App\Modules\Catalog\Interfaces\Http;

use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeAccess;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftVariantChange;
use App\Modules\Catalog\Application\Drafts\UpdateLocalDraftVariant;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeInput;
use App\Shared\Http\ApiResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;

final class LocalDraftVariantChangeController
{
    public function update(Request $request, string $catalogPublicId, string $productPublicId, string $variantPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftVariantChangeAccess::UPDATE);
        abort_unless($request->isJson(), 415);
        $etag = $request->header('If-Match');
        if ($etag === null) {
            return ApiResponse::error($request, 'precondition_required', 'Catalog version required.', 428);
        }
        $key = $request->header('Idempotency-Key');
        try {
            $body = json_decode($request->getContent(), false, 32, JSON_THROW_ON_ERROR);
            if ($request->query() !== [] || ! $body instanceof \stdClass || ! is_string($key) || ! preg_match('/^[!-~]{1,255}$/D', $key)) {
                throw new InvalidArgumentException;
            }
            $input = DraftVariantChangeInput::fromArray($catalogPublicId, $productPublicId, $variantPublicId, $etag, (array) $body);
        } catch (InvalidArgumentException|JsonException) {
            return $this->invalid($request);
        }
        try {
            $operation = app(UpdateLocalDraftVariant::class)->execute($actor, $input, $key, (string) $request->attributes->get('correlation_id'));
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_variant_change_operation', $operation->publicId, $operation->snapshot());
    }

    public function read(Request $request, string $operationPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftVariantChangeAccess::READ);
        if (! DraftCatalogInput::publicId($operationPublicId) || $request->query() !== []) {
            return $this->invalid($request);
        }
        try {
            $operation = app(ReadLocalDraftVariantChange::class)->execute($actor, $operationPublicId);
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_variant_change_operation', $operation->publicId, $operation->snapshot());
    }

    private function authorize(Request $request, string $capability): int
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $id = $request->user('web')?->getAuthIdentifier();
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 401);
        abort_if(app(LocalDraftVariantChangeAccess::class)->actor((int) $id, $capability, new DateTimeImmutable('now', new DateTimeZone('UTC'))) === null, 403);

        return (int) $id;
    }

    private function invalid(Request $request): JsonResponse
    {
        return ApiResponse::error($request, 'validation_failed', 'Invalid draft variant change request.', 422);
    }

    private function failure(Request $request, DraftCatalogFailure $failure): JsonResponse
    {
        $code = $failure->getMessage();
        $status = match ($code) {
            'forbidden' => 403, 'validation_failed' => 422, 'not_found' => 404,
            'version_conflict', 'no_changes', 'idempotency_mismatch', 'idempotency_expired' => 409, default => 500
        };

        return ApiResponse::error($request, $status === 500 ? 'internal_error' : $code, 'Draft variant change rejected.', $status);
    }
}
