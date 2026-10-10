<?php

namespace App\Modules\Marketplace\Interfaces\Console;

use App\Modules\Marketplace\Application\Commerce\ProbeLocalCommercialContext as CommercialContextProbe;
use App\Modules\Marketplace\Domain\Commerce\BranchPublicId;
use App\Modules\Marketplace\Domain\Commerce\CommercialContext;
use App\Modules\Marketplace\Domain\Commerce\MerchantPublicId;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

final class ProbeLocalCommercialContext extends Command
{
    protected $signature = 'marketplace:local-commercial-context {merchant_public_id} {market_public_id} {branch_public_id}';

    protected $description = 'Diagnosticar la relación draft comercio/mercado/sucursal exclusivamente en local/testing';

    public function handle(): int
    {
        if (! $this->laravel->environment(['local', 'testing'])) {
            $this->error('Local commercial context diagnostic required.');

            return self::FAILURE;
        }
        $merchant = $this->argument('merchant_public_id');
        $market = $this->argument('market_public_id');
        $branch = $this->argument('branch_public_id');
        try {
            $context = new CommercialContext(new MerchantPublicId($merchant), new MarketPublicId($market), new BranchPublicId($branch));
        } catch (InvalidArgumentException) {
            $this->error('Invalid local commercial context input.');

            return self::FAILURE;
        }
        try {
            $matched = $this->laravel->make(CommercialContextProbe::class)->evaluate($context);
        } catch (Throwable) {
            $this->error('Local commercial context diagnostic unavailable.');

            return self::FAILURE;
        }
        $this->line(json_encode([
            'fixture_version' => CommercialContextProbe::VERSION,
            'status' => $matched ? 'matched' : 'not_found',
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
