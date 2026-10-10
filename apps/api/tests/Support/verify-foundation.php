<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
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
    if (app()->environment(['local', 'testing'])) {
        $coverage = Http::timeout(15)->get('http://nginx/api/v1/marketplace/local-coverage-probe', ['longitude' => 3.5, 'latitude' => 0.5]);
        foundationAssert($coverage->status() === 200 && $coverage->json('data.type') === 'local_coverage_probe'
            && $coverage->json('data.id') === 'local-coverage-v1' && $coverage->json('data.attributes') === ['status' => 'selected', 'zone_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0'], 'local HTTP coverage uses real PostGIS fixture');
        foundationAssert($coverage->header('X-Correlation-ID') === $coverage->json('meta.correlation_id')
            && str_contains($coverage->header('Cache-Control'), 'no-store') && ! $coverage->header('Set-Cookie'), 'coverage response preserves correlation, privacy and no session');
        $identityTables = ['users', 'identity_permissions', 'identity_permission_grants'];
        $identityCounts = array_map(fn ($table) => DB::table($table)->count(), $identityTables);
        $persisted = Http::timeout(15)->acceptJson()->get('http://nginx/api/v1/marketplace/local-persisted-coverage-probe');
        foundationAssert($persisted->status() === 401 && $persisted->json('error.code') === 'unauthenticated'
            && str_contains($persisted->header('Cache-Control'), 'no-store')
            && $persisted->header('X-Correlation-ID') === $persisted->json('error.correlation_id'), 'persisted coverage HTTP requires session and preserves privacy');
        foundationAssert($identityCounts === array_map(fn ($table) => DB::table($table)->count(), $identityTables), 'anonymous persisted diagnostic creates no users or permissions');
        $fixtureTables = ['countries', 'markets', 'service_zones', 'marketplace_local_fixture_operations', 'merchants', 'branches', 'platform_idempotency_keys'];
        $fixtureCounts = array_map(fn ($table) => DB::table($table)->count(), $fixtureTables);
        $creation = Http::timeout(15)->acceptJson()->withHeaders(['Idempotency-Key' => 'synthetic-foundation-key'])->post('http://nginx/api/v1/marketplace/local-draft-fixtures', ['fixture_profile' => 'synthetic-origin-a-v1']);
        foundationAssert($creation->status() === 419 && $creation->json('error.code') === 'csrf_token_mismatch'
            && str_contains($creation->header('Cache-Control'), 'no-store')
            && $creation->header('X-Correlation-ID') === $creation->json('error.correlation_id'), 'real fixture creation requires CSRF before session access');
        foundationAssert($fixtureCounts === array_map(fn ($table) => DB::table($table)->count(), $fixtureTables)
            && $identityCounts === array_map(fn ($table) => DB::table($table)->count(), $identityTables), 'anonymous fixture creation changes no foundation data or idempotency claims');
    }
    foundationAssert(array_values(array_map('basename', glob(app_path('Modules/*'), GLOB_ONLYDIR))) === ['Identity', 'Marketplace', 'Platform'], 'only approved Identity, Marketplace and Platform modules are materialized');
    $tables = array_map(fn ($row) => $row->tablename, DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"));
    foundationAssert(array_intersect(['orders', 'payments', 'products', 'stores', 'inventories'], $tables) === [], 'no ordering, payment, catalog or inventory tables');
    foreach (['countries', 'markets', 'service_zones', 'marketplace_local_fixture_operations', 'merchants', 'branches'] as $table) {
        foundationAssert(in_array($table, $tables, true) && DB::table($table)->count() === 0, 'approved Marketplace foundation table remains empty');
    }
    if (app()->environment(['local', 'testing'])) {
        $exit = Artisan::call('marketplace:local-persisted-coverage', ['market_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAZ', 'longitude' => '0', 'latitude' => '0']);
        $diagnostic = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        foundationAssert($exit === 0 && $diagnostic === ['fixture_version' => 'local-persisted-coverage-v1', 'status' => 'market_not_found', 'zone_id' => null], 'persisted geographic diagnostic reads empty foundation without fixtures');
        $contextTables = array_values(array_unique(array_merge($tables, ['migrations'])));
        $counts = array_map(fn ($table) => DB::table($table)->count(), $contextTables);
        $exit = Artisan::call('marketplace:local-commercial-context', ['merchant_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAZ', 'market_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0', 'branch_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB1']);
        $diagnostic = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        foundationAssert($exit === 0 && $diagnostic === ['fixture_version' => 'local-commercial-context-v1', 'status' => 'not_found'], 'commercial context diagnostic reads empty foundation without private details');
        foundationAssert($counts === array_map(fn ($table) => DB::table($table)->count(), $contextTables), 'commercial context diagnostic preserves all foundation counts');
    }
    foundationAssert(array_intersect(['zone_rules', 'addresses', 'geocoding_results', 'merchant_documents', 'merchant_contracts', 'commission_rules', 'branch_schedules', 'branch_schedule_exceptions', 'branch_service_areas', 'branch_settings', 'branch_memberships'], $tables) === [], 'no operational Marketplace configuration tables');
    foundationAssert(DB::table('users')->whereNotNull('phone_key')->whereNull('public_id')->count() === 0, 'local identities have public identifiers');
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
