<?php

namespace App\Modules\Catalog\Application\Drafts;

use App\Modules\Catalog\Domain\Drafts\DraftProductCursor;

interface LocalDraftProductCursorCodec
{
    public function encode(DraftProductCursor $cursor): string;

    public function decode(string $token): DraftProductCursor;
}
