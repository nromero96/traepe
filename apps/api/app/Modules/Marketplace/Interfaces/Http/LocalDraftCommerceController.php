<?php

namespace App\Modules\Marketplace\Interfaces\Http;

use App\Modules\Marketplace\Application\Commerce\CreateLocalDraftCommerce;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceAccess;
use App\Modules\Marketplace\Application\Commerce\ReadLocalDraftCommerce;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceFailure;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Shared\Http\ApiResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;

final class LocalDraftCommerceController
{
    public function create(Request $request): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftCommerceAccess::CREATE);
        abort_unless($request->isJson(), 415);
        $key = $request->header('Idempotency-Key');
        try {
            $body = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($body) || ! is_string($key) || ! preg_match('/^[!-~]{1,255}$/D', $key)) {
                throw new InvalidArgumentException;
            }
            $input = DraftCommerceInput::fromArray($body);
        } catch (InvalidArgumentException|JsonException) {
            return ApiResponse::error($request, 'validation_failed', 'Invalid draft commerce request.', 422);
        }
        try {
            $operation = app(CreateLocalDraftCommerce::class)->execute($actor, $input, $key, (string) $request->attributes->get('correlation_id'));
        } catch (DraftCommerceFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_commerce_operation', $operation->publicId, $operation->snapshot(), 201);
    }

    public function read(Request $request, string $operationPublicId): JsonResponse
    {
        $actor = $this->authorize($request, LocalDraftCommerceAccess::READ);
        if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $operationPublicId)) {
            return ApiResponse::error($request, 'validation_failed', 'Invalid draft commerce request.', 422);
        }
        try {
            $operation = app(ReadLocalDraftCommerce::class)->execute($actor, $operationPublicId);
        } catch (DraftCommerceFailure $failure) {
            return $this->failure($request, $failure);
        }

        return ApiResponse::resource($request, 'local_draft_commerce_operation', $operation->publicId, $operation->snapshot());
    }

    private function authorize(Request $request, string $capability): int
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $id = $request->user('web')?->getAuthIdentifier();
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 401);
        abort_if(app(LocalDraftCommerceAccess::class)->actor((int) $id, $capability, new DateTimeImmutable('now', new DateTimeZone('UTC'))) === null, 403);

        return (int) $id;
    }

    private function failure(Request $request, DraftCommerceFailure $failure): JsonResponse
    {
        $code = $failure->getMessage();
        $status = match ($code) {
            'forbidden' => 403,
            'not_found' => 404,
            'market_not_available', 'idempotency_mismatch', 'idempotency_expired' => 409,
            default => 500,
        };

        return ApiResponse::error($request, $status === 500 ? 'internal_error' : $code, 'Draft commerce operation rejected.', $status);
    }
}
