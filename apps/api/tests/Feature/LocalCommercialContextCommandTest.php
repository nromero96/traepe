<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Application\Commerce\LocalCommercialContextSource;
use App\Modules\Marketplace\Domain\Commerce\BranchPublicId;
use App\Modules\Marketplace\Domain\Commerce\CommercialContext;
use App\Modules\Marketplace\Domain\Commerce\MerchantPublicId;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use App\Modules\Marketplace\Infrastructure\Commerce\PostgresLocalCommercialContextSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalCommercialContextCommandTest extends TestCase
{
    private const MERCHANT = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private const MARKET = '01ARZ3NDEKTSV4RRFFQ69G5FB0';

    private const BRANCH = '01ARZ3NDEKTSV4RRFFQ69G5FB1';

    private function arguments(): array
    {
        return ['merchant_public_id' => self::MERCHANT, 'market_public_id' => self::MARKET, 'branch_public_id' => self::BRANCH];
    }

    public function test_command_emits_only_version_and_status_for_both_results(): void
    {
        foreach ([true => 'matched', false => 'not_found'] as $matched => $status) {
            $this->mock(LocalCommercialContextSource::class)->shouldReceive('matches')->once()
                ->with(Mockery::on(fn (CommercialContext $context) => $context->merchant->value === self::MERCHANT && $context->market->value === self::MARKET && $context->branch->value === self::BRANCH))
                ->andReturn((bool) $matched);
            $this->assertSame(0, Artisan::call('marketplace:local-commercial-context', $this->arguments()));
            $this->assertSame(['fixture_version' => 'local-commercial-context-v1', 'status' => $status], json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR));
        }
    }

    public function test_each_invalid_argument_fails_before_resolving_the_port(): void
    {
        $resolved = false;
        $this->app->bind(LocalCommercialContextSource::class, function () use (&$resolved): never {
            $resolved = true;
            throw new RuntimeException('private-port-resolution');
        });
        foreach (array_keys($this->arguments()) as $argument) {
            foreach (['1', 'private-invalid-reference', strtolower(self::MERCHANT), '8'.substr(self::MERCHANT, 1), ' '.self::MERCHANT, self::MERCHANT.' ', self::MERCHANT."\n", self::MERCHANT."\0", substr(self::MERCHANT, 0, 25).'I'] as $invalid) {
                $this->assertSame(1, Artisan::call('marketplace:local-commercial-context', array_replace($this->arguments(), [$argument => $invalid])));
                $this->assertSame('Invalid local commercial context input.', trim(Artisan::output()));
            }
        }
        $this->assertFalse($resolved);
    }

    public function test_source_and_resolution_failures_emit_generic_errors(): void
    {
        $this->mock(LocalCommercialContextSource::class)->shouldReceive('matches')->once()->andThrow(new RuntimeException('private-sql-bindings-and-commercial-details'));
        $this->assertSame(1, Artisan::call('marketplace:local-commercial-context', $this->arguments()));
        $this->assertSame('Local commercial context diagnostic unavailable.', trim(Artisan::output()));
        unset($this->app[LocalCommercialContextSource::class]);
        $this->app->bind(LocalCommercialContextSource::class, fn () => throw new RuntimeException('private-binding-details'));
        $this->assertSame(1, Artisan::call('marketplace:local-commercial-context', $this->arguments()));
        $this->assertSame('Local commercial context diagnostic unavailable.', trim(Artisan::output()));
    }

    public function test_registered_command_denies_non_local_before_validation_or_resolution(): void
    {
        unset($this->app[LocalCommercialContextSource::class]);
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            foreach ([$this->arguments(), array_replace($this->arguments(), ['branch_public_id' => 'private-invalid-reference'])] as $arguments) {
                $this->assertSame(1, Artisan::call('marketplace:local-commercial-context', $arguments));
                $this->assertSame('Local commercial context diagnostic required.', trim(Artisan::output()));
            }
        }
    }

    public function test_adapter_denies_non_local_before_sql(): void
    {
        DB::shouldReceive('select')->never();
        $context = new CommercialContext(new MerchantPublicId(self::MERCHANT), new MarketPublicId(self::MARKET), new BranchPublicId(self::BRANCH));
        foreach (['production', 'staging'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            try {
                (new PostgresLocalCommercialContextSource)->matches($context);
                $this->fail('Non-local commercial diagnostic accepted.');
            } catch (RuntimeException $failure) {
                $this->assertSame('Local commercial context diagnostic required.', $failure->getMessage());
            }
        }
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
                'command_registered' => array_key_exists('marketplace:local-commercial-context', $kernel->all()),
                'port_bound' => $app->bound(App\Modules\Marketplace\Application\Commerce\LocalCommercialContextSource::class),
            ], JSON_THROW_ON_ERROR);
            PHP;
        foreach (['production', 'staging'] as $environment) {
            $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => $environment]);
            $process->mustRun();
            $this->assertSame(['environment' => $environment, 'command_registered' => false, 'port_bound' => false], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
        }
    }
}
