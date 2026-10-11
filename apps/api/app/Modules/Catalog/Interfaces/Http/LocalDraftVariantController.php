<?php

namespace App\Modules\Catalog\Interfaces\Http;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftVariant;
use App\Modules\Catalog\Application\Drafts\ListLocalDraftVariants;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantAccess;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftVariant;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Shared\Http\ApiResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;

final class LocalDraftVariantController
{
    public function create(Request $request, string $catalogPublicId, string $productPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftVariantAccess::CREATE);
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
            $input = DraftVariantInput::fromArray($catalogPublicId, $productPublicId, $etag, (array) $body);
        } catch (InvalidArgumentException|JsonException) {
            return $this->invalid($request);
        }
        try {
            $operation = app(CreateLocalDraftVariant::class)->execute($actor, $input, $key, (string) $request->attributes->get('correlation_id'));
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_variant_operation', $operation->publicId, $operation->snapshot(), 201);
    }

    public function variants(Request $request, string $catalogPublicId, string $productPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftVariantAccess::CONTENT);
        $query = $request->query();
        $limit = $query['limit'] ?? '20';
        $token = $query['cursor'] ?? null;
        if (! DraftCatalogInput::publicId($catalogPublicId) || ! DraftCatalogInput::publicId($productPublicId) || array_diff(array_keys($query), ['limit', 'cursor']) !== []
            || ! is_string($limit) || ! preg_match('/^[1-9][0-9]?$/D', $limit) || (int) $limit > 50
            || (array_key_exists('cursor', $query) && (! is_string($token) || $token === '' || strlen($token) > 4096))) {
            return $this->invalid($request);
        }
        try {
            $page = app(ListLocalDraftVariants::class)->execute($actor, $catalogPublicId, $productPublicId, (int) $limit, $token);
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }
        $data = array_map(fn ($product) => ['type' => 'local_draft_variant', 'id' => $product->publicId, 'attributes' => $product->snapshot()], $page->variants);

        return response()->json(['data' => $data, 'meta' => ['correlation_id' => $request->attributes->get('correlation_id'), 'catalog_version' => $page->catalogVersion, 'product_version' => $page->productVersion,
            'next_cursor' => $page->nextCursor, 'has_more' => $page->hasMore, 'filters' => ['catalog_public_id' => $catalogPublicId, 'product_public_id' => $productPublicId, 'limit' => (int) $limit]]])->header('ETag', '"catalog:'.$catalogPublicId.':v'.$page->catalogVersion.'"');
    }

    public function read(Request $request, string $operationPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftVariantAccess::READ);
        if (! DraftCatalogInput::publicId($operationPublicId) || $request->query() !== []) {
            return $this->invalid($request);
        }
        try {
            $operation = app(ReadLocalDraftVariant::class)->execute($actor, $operationPublicId);
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_variant_operation', $operation->publicId, $operation->snapshot());
    }

    private function authorize(Request $request, string $capability): int
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $id = $request->user('web')?->getAuthIdentifier();
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 401);
        abort_if(app(LocalDraftVariantAccess::class)->actor((int) $id, $capability, new DateTimeImmutable('now', new DateTimeZone('UTC'))) === null, 403);

        return (int) $id;
    }

    private function invalid(Request $request): JsonResponse
    {
        return ApiResponse::error($request, 'validation_failed', 'Invalid draft variant request.', 422);
    }

    private function failure(Request $request, DraftCatalogFailure $failure): JsonResponse
    {
        $code = $failure->getMessage();
        $status = match ($code) {
            'forbidden' => 403,'validation_failed' => 422,'not_found' => 404,'version_conflict','sku_conflict','idempotency_mismatch','idempotency_expired','cursor_expired','catalog_changed' => 409,default => 500
        };

        return ApiResponse::error($request, $status === 500 ? 'internal_error' : $code, 'Draft product operation rejected.', $status);
    }
}
