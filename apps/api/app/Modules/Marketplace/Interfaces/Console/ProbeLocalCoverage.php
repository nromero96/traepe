<?php

namespace App\Modules\Marketplace\Interfaces\Console;

use App\Modules\Marketplace\Application\Coverage\LocalCoverageProbe;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

final class ProbeLocalCoverage extends Command
{
    protected $signature = 'marketplace:local-coverage {longitude} {latitude}';

    protected $description = 'Evaluar exclusivamente polígonos sintéticos de local-coverage-v1';

    public function handle(): int
    {
        // Check before resolving a local-only port, including a previously registered command.
        if (! $this->laravel->environment(['local', 'testing'])) {
            $this->error('Local coverage fixture required.');

            return self::FAILURE;
        }
        $longitude = filter_var($this->argument('longitude'), FILTER_VALIDATE_FLOAT);
        $latitude = filter_var($this->argument('latitude'), FILTER_VALIDATE_FLOAT);
        if ($longitude === false || $latitude === false) {
            $this->error('Invalid WGS84 point.');

            return self::FAILURE;
        }
        try {
            $point = new GeographicPoint($longitude, $latitude);
            $selection = $this->laravel->make(LocalCoverageProbe::class)->evaluate($point);
        } catch (InvalidArgumentException) {
            $this->error('Invalid WGS84 point.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Local coverage probe unavailable.');

            return self::FAILURE;
        }
        $this->line(json_encode([
            'fixture_version' => LocalCoverageProbe::VERSION,
            'status' => $selection->status, 'zone_id' => $selection->zonePublicId,
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
