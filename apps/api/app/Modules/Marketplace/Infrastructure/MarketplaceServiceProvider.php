<?php

namespace App\Modules\Marketplace\Infrastructure;

use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource;
use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Infrastructure\Coverage\IdentityLocalPersistedCoverageAccess;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalPersistedZoneSource;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalZoneSource;
use App\Modules\Marketplace\Interfaces\Console\ProbeLocalCoverage;
use App\Modules\Marketplace\Interfaces\Console\ProbeLocalPersistedCoverage;
use App\Modules\Marketplace\Interfaces\Http\LocalCoverageEnvironment;
use App\Modules\Marketplace\Interfaces\Http\LocalCoverageProbeController;
use App\Modules\Marketplace\Interfaces\Http\LocalPersistedCoverageProbeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment(['local', 'testing'])) {
            $this->app->bind(LocalZoneSource::class, PostgisLocalZoneSource::class);
            $this->app->bind(LocalPersistedZoneSource::class, PostgisLocalPersistedZoneSource::class);
            $this->app->bind(LocalPersistedCoverageAccess::class, IdentityLocalPersistedCoverageAccess::class);
        }
    }

    public function boot(): void
    {
        if (! $this->app->environment(['local', 'testing'])) {
            return;
        }
        if ($this->app->runningInConsole()) {
            $this->commands([ProbeLocalCoverage::class, ProbeLocalPersistedCoverage::class]);
        }
        Route::get('api/v1/marketplace/local-coverage-probe', LocalCoverageProbeController::class)
            ->middleware([LocalCoverageEnvironment::class, 'throttle:30,1,local-coverage:']);
        Route::get('api/v1/marketplace/local-persisted-coverage-probe', LocalPersistedCoverageProbeController::class)
            ->middleware([LocalCoverageEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-persisted-coverage:']);
    }
}
