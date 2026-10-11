<?php

namespace App\Modules\Marketplace\Infrastructure\Commerce;

use App\Modules\Marketplace\Application\Commerce\OwnedLocalDraftMerchantV1;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PostgresOwnedLocalDraftMerchantV1 implements OwnedLocalDraftMerchantV1
{
    public function lockOwnedDraft(string $merchantPublicId, string $actorPublicId): bool
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Owned merchant transaction required.');
        }
        foreach ([$merchantPublicId, $actorPublicId] as $reference) {
            if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $reference)) {
                return false;
            }
        }

        return DB::table('merchants as m')->join('marketplace_local_commerce_operations as o', 'o.merchant_id', '=', 'm.id')
            ->where('m.public_id', $merchantPublicId)->where('m.status', 'draft')->where('o.actor_public_id', $actorPublicId)
            ->select('m.public_id')->lock('FOR UPDATE OF m')->first() !== null;
    }
}
