<?php

namespace App\Modules\Catalog\Interfaces\Http;

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\ListLocalDraftProducts;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftCatalogProfile;
use App\Modules\Catalog\Application\Drafts\ReadLocalDraftProduct;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Shared\Http\ApiResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;

final class LocalDraftProductController
{
    public function create(Request $request, string $catalogPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftProductAccess::CREATE);
        abort_unless($request->isJson(), 415);
        $etag = $request->header('If-Match');
        if ($etag === null) {
            return ApiResponse::error($request, 'precondition_required', 'Catalog version required.', 428);
        }
        $key = $request->header('Idempotency-Key');
        try {
            $body = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if ($request->query() !== [] || ! is_array($body) || ! is_string($key) || ! preg_match('/^[!-~]{1,255}$/D', $key)) {
                throw new InvalidArgumentException;
            }
            $input = DraftProductInput::fromArray($catalogPublicId, $etag, $body);
        } catch (InvalidArgumentException|JsonException) {
            return $this->invalid($request);
        }
        try {
            $operation = app(CreateLocalDraftProduct::class)->execute($actor, $input, $key, (string) $request->attributes->get('correlation_id'));
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_product_operation', $operation->publicId, $operation->snapshot(), 201);
    }

    public function profile(Request $request, string $catalogPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftProductAccess::CONTENT);
        if (! DraftCatalogInput::publicId($catalogPublicId) || $request->query() !== []) {
            return $this->invalid($request);
        }
        try {
            $profile = app(ReadLocalDraftCatalogProfile::class)->execute($actor, $catalogPublicId);
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_catalog_profile', $profile->publicId, $profile->snapshot())->header('ETag', $profile->etag());
    }

    public function products(Request $request, string $catalogPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftProductAccess::CONTENT);
        $query = $request->query();
        $limit = $query['limit'] ?? '20';
        $token = $query['cursor'] ?? null;
        if (! DraftCatalogInput::publicId($catalogPublicId) || array_diff(array_keys($query), ['limit', 'cursor']) !== []
            || ! is_string($limit) || ! preg_match('/^[1-9][0-9]?$/D', $limit) || (int) $limit > 50
            || (array_key_exists('cursor', $query) && (! is_string($token) || $token === '' || strlen($token) > 4096))) {
            return $this->invalid($request);
        }
        try {
            $page = app(ListLocalDraftProducts::class)->execute($actor, $catalogPublicId, (int) $limit, $token);
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }
        $data = array_map(fn ($product) => ['type' => 'local_draft_product', 'id' => $product->publicId, 'attributes' => $product->snapshot()], $page->products);

        return response()->json(['data' => $data, 'meta' => ['correlation_id' => $request->attributes->get('correlation_id'), 'catalog_version' => $page->catalogVersion,
            'next_cursor' => $page->nextCursor, 'has_more' => $page->hasMore, 'filters' => ['catalog_public_id' => $catalogPublicId, 'limit' => (int) $limit]]]);
    }

    public function read(Request $request, string $operationPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftProductAccess::READ);
        if (! DraftCatalogInput::publicId($operationPublicId) || $request->query() !== []) {
            return $this->invalid($request);
        }
        try {
            $operation = app(ReadLocalDraftProduct::class)->execute($actor, $operationPublicId);
        } catch (DraftCatalogFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_product_operation', $operation->publicId, $operation->snapshot());
    }

    private function authorize(Request $request, string $capability): int
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $id = $request->user('web')?->getAuthIdentifier();
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 401);
        abort_if(app(LocalDraftProductAccess::class)->actor((int) $id, $capability, new DateTimeImmutable('now', new DateTimeZone('UTC'))) === null, 403);

        return (int) $id;
    }

    private function invalid(Request $request): JsonResponse
    {
        return ApiResponse::error($request, 'validation_failed', 'Invalid draft product request.', 422);
    }

    private function failure(Request $request, DraftCatalogFailure $failure): JsonResponse
    {
        $code = $failure->getMessage();
        $status = match ($code) {
            'forbidden' => 403,'validation_failed' => 422,'not_found' => 404,'version_conflict','idempotency_mismatch','idempotency_expired','cursor_expired','catalog_changed' => 409,default => 500
        };

        return ApiResponse::error($request, $status === 500 ? 'internal_error' : $code, 'Draft product operation rejected.', $status);
    }
}
