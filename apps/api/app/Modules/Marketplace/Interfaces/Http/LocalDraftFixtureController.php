<?php

namespace App\Modules\Marketplace\Interfaces\Http;

use App\Modules\Marketplace\Application\Fixtures\CreateLocalDraftFixture;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use App\Modules\Marketplace\Domain\Fixtures\FixtureFailure;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use App\Shared\Http\ApiResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

final class LocalDraftFixtureController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $id = $request->user('web')?->getAuthIdentifier();
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 401);
        abort_if(app(LocalDraftFixtureAccess::class)->actor((int) $id, new DateTimeImmutable('now', new DateTimeZone('UTC'))) === null, 403);
        abort_unless($request->isJson(), 415);
        try {
            // Decode the original body: global input trimming must not normalize this closed profile.
            $body = json_decode($request->getContent(), false, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->invalid($request);
        }
        $key = $request->header('Idempotency-Key');
        if (! $body instanceof \stdClass || array_keys(get_object_vars($body)) !== ['fixture_profile']
            || ! is_string($body->fixture_profile) || ($profile = FixtureProfile::tryFrom($body->fixture_profile)) === null
            || ! is_string($key) || ! preg_match('/^[!-~]{1,255}$/D', $key)) {
            return $this->invalid($request);
        }
        try {
            $operation = app(CreateLocalDraftFixture::class)->execute((int) $id, $profile, $key, (string) $request->attributes->get('correlation_id'));
        } catch (FixtureFailure $failure) {
            $status = $failure->getMessage() === 'forbidden' ? 403 : 409;

            return ApiResponse::error($request, $failure->getMessage(), $status === 403 ? 'Access denied.' : 'Fixture operation rejected.', $status);
        }

        return ApiResponse::resource($request, 'local_draft_fixture_operation', $operation->publicId, $operation->snapshot(), 201);
    }

    private function invalid(Request $request): JsonResponse
    {
        return ApiResponse::error($request, 'validation_failed', 'Invalid fixture request.', 422);
    }
}
