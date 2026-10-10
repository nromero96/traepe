<?php

namespace App\Modules\Marketplace\Interfaces\Console;

use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageProbe;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

final class ProbeLocalPersistedCoverage extends Command
{
    protected $signature = 'marketplace:local-persisted-coverage {market_public_id} {longitude} {latitude}';

    protected $description = 'Diagnosticar zonas draft/fixture del mercado indicado exclusivamente en local/testing';

    public function handle(): int
    {
        if (! $this->laravel->environment(['local', 'testing'])) {
            $this->error('Local persisted coverage diagnostic required.');

            return self::FAILURE;
        }
        $reference = $this->argument('market_public_id');
        $longitude = filter_var($this->argument('longitude'), FILTER_VALIDATE_FLOAT);
        $latitude = filter_var($this->argument('latitude'), FILTER_VALIDATE_FLOAT);
        if ($longitude === false || $latitude === false) {
            $this->error('Invalid local geographic diagnostic input.');

            return self::FAILURE;
        }
        try {
            $market = new MarketPublicId($reference);
            $point = new GeographicPoint($longitude, $latitude);
            $result = $this->laravel->make(LocalPersistedCoverageProbe::class)->evaluate($market, $point);
        } catch (InvalidArgumentException) {
            $this->error('Invalid local geographic diagnostic input.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Local persisted coverage diagnostic unavailable.');

            return self::FAILURE;
        }
        $this->line(json_encode([
            'fixture_version' => LocalPersistedCoverageProbe::VERSION,
            'status' => $result->status, 'zone_id' => $result->zonePublicId,
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
