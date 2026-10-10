<?php

namespace App\Modules\Marketplace\Infrastructure;

use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalZoneSource;
use App\Modules\Marketplace\Interfaces\Console\ProbeLocalCoverage;
use App\Modules\Marketplace\Interfaces\Http\LocalCoverageEnvironment;
use App\Modules\Marketplace\Interfaces\Http\LocalCoverageProbeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment(['local', 'testing'])) {
            $this->app->bind(LocalZoneSource::class, PostgisLocalZoneSource::class);
        }
    }

    public function boot(): void
    {
        if (! $this->app->environment(['local', 'testing'])) {
            return;
        }
        if ($this->app->runningInConsole()) {
            $this->commands([ProbeLocalCoverage::class]);
        }
        Route::get('api/v1/marketplace/local-coverage-probe', LocalCoverageProbeController::class)
            ->middleware([LocalCoverageEnvironment::class, 'throttle:30,1,local-coverage:']);
    }
}
