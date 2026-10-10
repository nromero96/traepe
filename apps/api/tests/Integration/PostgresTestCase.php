<?php

namespace Tests\Integration;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class PostgresTestCase extends TestCase
{
    protected ?string $probeDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('TRAEPE_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Set TRAEPE_INTEGRATION_TESTS=1 in Docker to run isolated real PostgreSQL checks.');
        }
        // Databases are isolated but fixture user IDs repeat; limiter state must be too.
        // This affects only the test container and never flushes shared Redis data.
        config(['cache.limiter' => 'array']);
        $this->app->forgetInstance(RateLimiter::class);
        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'postgres']);
        DB::purge('pgsql');
        $name = 'traepe_00e_test_'.bin2hex(random_bytes(8));
        DB::statement('CREATE DATABASE "'.$name.'"');
        $this->probeDatabase = $name;
        config(['database.connections.pgsql.database' => $name]);
        DB::purge('pgsql');
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
    }

    protected function tearDown(): void
    {
        try {
            if ($this->probeDatabase !== null) {
                DB::purge('pgsql');
                config(['database.connections.pgsql.database' => 'postgres']);
                DB::statement('DROP DATABASE "'.$this->probeDatabase.'" WITH (FORCE)');
            }
        } finally {
            parent::tearDown();
        }
    }
}
