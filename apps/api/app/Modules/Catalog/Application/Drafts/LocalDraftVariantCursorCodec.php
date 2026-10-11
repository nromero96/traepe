<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;

interface LocalDraftVariantCursorCodec
{
    public function encode(DraftVariantCursor $cursor): string;

    public function decode(string $token): DraftVariantCursor;
}
