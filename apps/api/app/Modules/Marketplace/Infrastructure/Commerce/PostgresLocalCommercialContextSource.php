<?php

namespace App\Modules\Marketplace\Infrastructure\Commerce;

use App\Modules\Marketplace\Application\Commerce\LocalCommercialContextSource;
use App\Modules\Marketplace\Domain\Commerce\CommercialContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PostgresLocalCommercialContextSource implements LocalCommercialContextSource
{
    public function matches(CommercialContext $context): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local commercial context diagnostic required.');
        }

        // DP-033: one snapshot, explicit scope, and no commercial details selected.
        return DB::select(<<<'SQL'
            SELECT 1 AS matched
            FROM branches AS branch
            JOIN merchants AS merchant ON merchant.id = branch.merchant_id
            JOIN markets AS market ON market.id = branch.market_id
            WHERE merchant.public_id = ? AND market.public_id = ? AND branch.public_id = ?
              AND merchant.status = 'draft' AND market.status = 'draft' AND branch.status = 'draft'
            SQL, [$context->merchant->value, $context->market->value, $context->branch->value]) !== [];
    }
}
