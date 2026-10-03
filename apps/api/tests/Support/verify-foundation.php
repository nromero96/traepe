<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function foundationAssert(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException('Foundation assertion failed.');
    }
    echo "PASS: {$label}\n";
}

try {
    foundationAssert(Http::timeout(15)->get('http://nginx/up')->successful(), 'real HTTP liveness');
    $ready = Http::timeout(15)->get('http://nginx/api/v1/health/ready');
    foundationAssert($ready->successful() && $ready->json('data.attributes.status') === 'ready', 'real HTTP readiness of all dependencies');
    foundationAssert($ready->header('X-Correlation-ID') === $ready->json('meta.correlation_id'), 'real HTTP response correlation');
    foundationAssert(Http::timeout(15)->get('http://nginx/horizon')->status() === 401, 'Horizon denies anonymous request');
    $missing = Http::timeout(15)->get('http://nginx/api/v1/absent');
    foundationAssert($missing->status() === 404 && $missing->json('error.code') === 'not_found', 'unregistered API route returns sanitized error');
    foundationAssert(array_values(array_map('basename', glob(app_path('Modules/*'), GLOB_ONLYDIR))) === ['Platform'], 'only Platform module is materialized');
    $tables = array_map(fn ($row) => $row->tablename, DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"));
    foundationAssert(array_intersect(['orders', 'payments', 'products', 'stores', 'inventories'], $tables) === [], 'no commercial tables');
    foundationAssert(DB::table('users')->count() === 0, 'Laravel starter users table remains empty; no identity flow implemented');
    foundationAssert(config('logging.default') === 'safe' && config('app.debug') === false, 'safe logging and debug disabled');
    $log = file_get_contents(storage_path('logs/technical.jsonl'));
    foreach (['app.key', 'database.connections.pgsql.password', 'database.redis.default.password', 'technical.password', 'filesystems.disks.s3.secret', 'broadcasting.connections.reverb.secret'] as $setting) {
        $value = config($setting);
        foundationAssert(is_string($value) && strlen($value) >= 32 && ! str_contains($log, $value), 'local credential excluded from technical logs');
    }
    foundationAssert(DB::select("SELECT datname FROM pg_database WHERE datname LIKE 'traepe_00e_test_%' OR datname LIKE 'traepe_00c_probe_%'") === [], 'no temporary integration database remains');
} catch (Throwable) {
    fwrite(STDERR, "Foundation verification failed; internal details suppressed.\n");
    exit(1);
}
