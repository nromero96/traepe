<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalPersistedZoneSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalPersistedCoverageCommandTest extends TestCase
{
    private const MARKET = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private function arguments(): array
    {
        return ['market_public_id' => self::MARKET, 'longitude' => '0.5', 'latitude' => '0.5'];
    }

    public function test_command_emits_only_version_status_and_public_zone(): void
    {
        $zone = '01ARZ3NDEKTSV4RRFFQ69G5FB0';
        $this->mock(LocalPersistedZoneSource::class)->shouldReceive('matches')->once()->andReturn([new ZoneCandidate($zone, 10)]);
        $this->assertSame(0, Artisan::call('marketplace:local-persisted-coverage', $this->arguments()));
        $this->assertSame(['fixture_version' => 'local-persisted-coverage-v1', 'status' => 'selected', 'zone_id' => $zone], json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_invalid_inputs_fail_before_accessing_the_source(): void
    {
        $this->mock(LocalPersistedZoneSource::class)->shouldNotReceive('matches');
        foreach ([['market_public_id' => 'private-invalid-reference'], ['market_public_id' => '81ARZ3NDEKTSV4RRFFQ69G5FAZ'], ['longitude' => 'NaN'], ['latitude' => 'INF'], ['longitude' => '-180.0001'], ['longitude' => '180.0001'], ['latitude' => '-90.0001'], ['latitude' => '90.0001']] as $changes) {
            $this->artisan('marketplace:local-persisted-coverage', array_replace($this->arguments(), $changes))
                ->expectsOutput('Invalid local geographic diagnostic input.')->assertFailed();
        }
    }

    public function test_registered_command_denies_non_local_before_resolving_an_unbound_port(): void
    {
        unset($this->app[LocalPersistedZoneSource::class]);
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->artisan('marketplace:local-persisted-coverage', $this->arguments())
                ->expectsOutput('Local persisted coverage diagnostic required.')->assertFailed();
        }
    }

    public function test_adapter_denies_non_local_before_sql(): void
    {
        DB::shouldReceive('select')->never();
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            try {
                (new PostgisLocalPersistedZoneSource)->matches(new MarketPublicId(self::MARKET), new GeographicPoint(0.5, 0.5));
                $this->fail('Non-local persisted diagnostic accepted.');
            } catch (RuntimeException $error) {
                $this->assertSame('Local persisted coverage diagnostic required.', $error->getMessage());
            }
        }
    }

    public function test_source_failure_has_a_generic_diagnostic_without_private_details(): void
    {
        $this->mock(LocalPersistedZoneSource::class)->shouldReceive('matches')->once()->andThrow(new RuntimeException('private-spatial-sql-and-bindings'));
        $this->assertSame(1, Artisan::call('marketplace:local-persisted-coverage', $this->arguments()));
        $this->assertSame('Local persisted coverage diagnostic unavailable.', trim(Artisan::output()));
    }

    public function test_fresh_non_local_processes_register_neither_command_nor_port(): void
    {
        $code = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();
            echo json_encode([
                'environment' => $app->environment(),
                'command_registered' => array_key_exists('marketplace:local-persisted-coverage', $kernel->all()),
                'port_bound' => $app->bound(App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource::class),
            ], JSON_THROW_ON_ERROR);
            PHP;
        foreach (['production', 'staging'] as $environment) {
            $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => $environment]);
            $process->mustRun();
            $this->assertSame(['environment' => $environment, 'command_registered' => false, 'port_bound' => false], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
        }
    }
}
