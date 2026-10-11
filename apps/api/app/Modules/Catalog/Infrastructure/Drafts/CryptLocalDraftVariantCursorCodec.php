<?php

namespace App\Modules\Catalog\Infrastructure\Drafts;

use App\Modules\Catalog\Application\Drafts\LocalDraftVariantCursorCodec;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Throwable;

final class CryptLocalDraftVariantCursorCodec implements LocalDraftVariantCursorCodec
{
    public function encode(DraftVariantCursor $cursor): string
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return rtrim(strtr(Crypt::encryptString(json_encode($cursor->snapshot(), JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    public function decode(string $token): DraftVariantCursor
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        try {
            if (! preg_match('/^[A-Za-z0-9_-]{1,4096}$/D', $token)) {
                throw new InvalidArgumentException;
            }
            $encrypted = strtr($token, '-_', '+/');
            $encrypted .= str_repeat('=', (4 - strlen($encrypted) % 4) % 4);
            $binary = base64_decode($encrypted, true);
            if ($binary === false || rtrim(strtr(base64_encode($binary), '+/', '-_'), '=') !== $token) {
                throw new InvalidArgumentException;
            }
            $value = json_decode(Crypt::decryptString($encrypted), true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($value)) {
                throw new InvalidArgumentException;
            }

            return DraftVariantCursor::restore($value);
        } catch (Throwable) {
            throw new DraftCatalogFailure('validation_failed');
        }
    }
}
