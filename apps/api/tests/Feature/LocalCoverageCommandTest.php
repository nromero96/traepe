<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Domain\Coverage\GeographicPoint;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalZoneSource;
use RuntimeException;
use Tests\TestCase;

final class LocalCoverageCommandTest extends TestCase
{
    public function test_invalid_command_inputs_do_not_access_the_source(): void
    {
        $this->mock(LocalZoneSource::class)->shouldNotReceive('matches');
        foreach ([['NaN', '0'], ['181', '0'], ['0', '90.1'], ['invalid-private-coordinate', '1']] as [$longitude, $latitude]) {
            $this->artisan('marketplace:local-coverage', compact('longitude', 'latitude'))
                ->expectsOutput('Invalid WGS84 point.')->assertFailed();
        }
    }

    public function test_non_local_environment_denies_before_resolving_an_unbound_port(): void
    {
        unset($this->app[LocalZoneSource::class]);
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->artisan('marketplace:local-coverage', ['longitude' => '0.5', 'latitude' => '0.5'])
                ->expectsOutput('Local coverage fixture required.')->assertFailed();
        }
    }

    public function test_the_adapter_itself_denies_before_sql_outside_local(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Local coverage fixture required.');
        (new PostgisLocalZoneSource)->matches(new GeographicPoint(0.5, 0.5));
    }

    public function test_source_failure_has_a_generic_diagnostic(): void
    {
        $this->mock(LocalZoneSource::class)->shouldReceive('matches')->once()->andThrow(new RuntimeException('private-source-debug-data'));
        $this->artisan('marketplace:local-coverage', ['longitude' => '0.5', 'latitude' => '0.5'])
            ->expectsOutput('Local coverage probe unavailable.')->assertFailed();
    }
}
