<?php

namespace Tests\Feature;

use App\Modules\Identity\Application\LocalOtp;
use App\Modules\Identity\Infrastructure\IdentityUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalIdentityEnvironmentTest extends TestCase
{
    private const ROUTES = [
        ['POST', '/api/v1/auth/otp/request'],
        ['POST', '/api/v1/auth/otp/verify'],
        ['GET', '/api/v1/auth/me'],
        ['POST', '/api/v1/auth/logout'],
    ];

    public static function nonLocalEnvironments(): array
    {
        return [['production'], ['staging']];
    }

    #[DataProvider('nonLocalEnvironments')]
    public function test_existing_local_routes_deny_before_authentication_csrf_and_storage(string $environment): void
    {
        $this->assertRoutesDenied($environment);
    }

    #[DataProvider('nonLocalEnvironments')]
    public function test_an_existing_authenticated_session_cannot_enable_local_routes(string $environment): void
    {
        // In-memory actor only; the guard must reject before any database lookup.
        $actor = (new IdentityUser)->forceFill(['id' => 1, 'public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'status' => 'active']);
        $this->actingAs($actor, 'web');
        $this->assertAuthenticatedAs($actor, 'web');
        $this->assertSame(1, $actor->getAuthIdentifier());
        $this->assertRoutesDenied($environment);
    }

    private function assertRoutesDenied(string $environment): void
    {
        config(['session.driver' => 'array']);
        $this->mock(LocalOtp::class, function ($mock): void {
            $mock->shouldNotReceive('request', 'verify', 'activeIdentity', 'recordSessionEnd');
        });
        // Routes were registered in testing, as when a local cache is reused.
        $this->app->detectEnvironment(fn () => $environment);
        foreach (self::ROUTES as [$method, $url]) {
            $this->json($method, $url)->assertNotFound()->assertJsonPath('error.code', 'not_found');
        }
    }

    public function test_a_real_local_route_cache_is_denied_by_fresh_non_local_processes(): void
    {
        $directory = sys_get_temp_dir().'/traepe_identity_routes_'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory, 0700));
        $cache = $directory.'/routes.php';
        $environment = [
            'APP_ENV' => 'local', 'APP_ROUTES_CACHE' => $cache,
            'APP_CONFIG_CACHE' => $directory.'/config.php',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
        ];
        try {
            $build = new Process([PHP_BINARY, 'artisan', 'route:cache', '--no-interaction'], base_path(), $environment);
            $this->assertSame(0, $build->setTimeout(30)->run());
            $this->assertFileExists($cache);
            foreach (['production', 'staging'] as $name) {
                $result = $this->probe($environment, $name);
                $this->assertSame($name, $result['environment']);
                $this->assertTrue($result['cached']);
                foreach ($result['routes'] as $route) {
                    $this->assertTrue($route['registered']);
                    $this->assertSame(404, $route['status']);
                    $this->assertSame('not_found', $route['error']);
                }
            }
            $this->assertTrue(unlink($cache));
            $fresh = $this->probe($environment, 'production');
            $this->assertFalse($fresh['cached']);
            foreach ($fresh['routes'] as $route) {
                $this->assertFalse($route['registered']);
                $this->assertSame(404, $route['status']);
            }
        } finally {
            // Remove only this test's file and empty directory; never the runtime cache.
            if (is_file($cache)) {
                unlink($cache);
            }
            rmdir($directory);
        }
    }

    private function probe(array $environment, string $name): array
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/identity-environment-worker.php')], base_path(), array_replace($environment, ['APP_ENV' => $name]));
        $this->assertSame(0, $process->setTimeout(30)->run());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
