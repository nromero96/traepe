<?php

namespace Tests\Integration;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LocalCommercialContextPostgresTest extends PostgresTestCase
{
    private function merchant(): array
    {
        $publicId = (string) Str::ulid();

        return ['id' => DB::table('merchants')->insertGetId(['public_id' => $publicId, 'legal_name' => 'Synthetic Merchant', 'trade_name' => 'Synthetic Merchant']), 'public_id' => $publicId];
    }

    private function market(): array
    {
        $country = DB::table('countries')->value('id') ?? DB::table('countries')->insertGetId(['public_id' => (string) Str::ulid(), 'code' => 'ZZ', 'name' => 'Synthetic Country', 'currency_code' => 'ZZZ']);
        $publicId = (string) Str::ulid();

        return ['id' => DB::table('markets')->insertGetId(['public_id' => $publicId, 'country_id' => $country, 'name' => 'Synthetic Market', 'timezone' => 'Etc/UTC', 'currency_code' => 'XXX']), 'public_id' => $publicId];
    }

    private function branch(array $merchant, array $market): array
    {
        $publicId = (string) Str::ulid();

        return ['id' => DB::table('branches')->insertGetId(['public_id' => $publicId, 'merchant_id' => $merchant['id'], 'market_id' => $market['id'], 'name' => 'Synthetic Branch', 'timezone' => 'Etc/UTC', 'point' => 'SRID=4326;POINT(0.5 1.5)']), 'public_id' => $publicId];
    }

    private function arguments(array $merchant, array $market, array $branch): array
    {
        return ['merchant_public_id' => $merchant['public_id'], 'market_public_id' => $market['public_id'], 'branch_public_id' => $branch['public_id']];
    }

    private function snapshot(): array
    {
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
        $snapshot = [];
        foreach ($tables as $table) {
            $rows = DB::table($table->tablename)->get()->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->sort()->values()->toJson();
            $snapshot[$table->tablename] = hash('sha256', $rows);
        }

        return $snapshot;
    }

    private function verifyRead(array $arguments, string $status): void
    {
        $before = $this->snapshot();
        $queries = 0;
        $safe = true;
        $record = true;
        DB::listen(function (QueryExecuted $event) use (&$queries, &$safe, &$record, $arguments): void {
            if (! $record) {
                return;
            }
            $queries++;
            // Only booleans/counts survive inspection; never print SQL or bindings.
            $safe = $safe && preg_match('/^\s*SELECT\s+1\s+AS\s+matched\b/i', $event->sql) === 1
                && preg_match('/\b(?:INSERT|UPDATE|DELETE|identity_\w+|users|platform_\w+|service_zones|countries|migrations)\b/i', $event->sql) === 0
                && str_contains($event->sql, "merchant.status = 'draft'")
                && str_contains($event->sql, "market.status = 'draft'")
                && str_contains($event->sql, "branch.status = 'draft'")
                && $event->bindings === array_values($arguments);
        });
        try {
            $exit = Artisan::call('marketplace:local-commercial-context', $arguments);
            $output = trim(Artisan::output());
        } finally {
            $record = false;
        }
        $this->assertSame(0, $exit);
        $this->assertSame(['fixture_version' => 'local-commercial-context-v1', 'status' => $status], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(1, $queries);
        $this->assertTrue($safe, 'Diagnostic exceeded its approved read scope.');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_empty_foundation_returns_not_found_and_preserves_every_table(): void
    {
        $this->verifyRead(['merchant_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAZ', 'market_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0', 'branch_public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB1'], 'not_found');
    }

    public function test_exact_context_supports_multiple_merchants_markets_and_branches_without_leaks(): void
    {
        $merchantA = $this->merchant();
        $merchantB = $this->merchant();
        $marketA = $this->market();
        $marketB = $this->market();
        $branchAA = $this->branch($merchantA, $marketA);
        $branchAA2 = $this->branch($merchantA, $marketA);
        $branchAB = $this->branch($merchantA, $marketB);
        $branchBA = $this->branch($merchantB, $marketA);
        foreach ([[$merchantA, $marketA, $branchAA], [$merchantA, $marketA, $branchAA2], [$merchantA, $marketB, $branchAB], [$merchantB, $marketA, $branchBA]] as [$merchant, $market, $branch]) {
            $this->verifyRead($this->arguments($merchant, $market, $branch), 'matched');
        }
        foreach ([[$merchantB, $marketA, $branchAA], [$merchantA, $marketB, $branchAA], [$merchantB, $marketB, $branchAA], [$merchantA, $marketA, $branchAB], [$merchantA, $marketA, $branchBA]] as [$merchant, $market, $branch]) {
            $this->verifyRead($this->arguments($merchant, $market, $branch), 'not_found');
        }
    }

    public function test_unknown_references_are_indistinguishable_and_do_not_change_records(): void
    {
        $merchant = $this->merchant();
        $market = $this->market();
        $branch = $this->branch($merchant, $market);
        $arguments = $this->arguments($merchant, $market, $branch);
        foreach (array_keys($arguments) as $argument) {
            $this->verifyRead(array_replace($arguments, [$argument => (string) Str::ulid()]), 'not_found');
        }
        $this->verifyRead(array_map(fn () => (string) Str::ulid(), $arguments), 'not_found');
    }
}
