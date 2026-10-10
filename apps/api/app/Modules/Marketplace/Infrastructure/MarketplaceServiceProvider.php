<?php

namespace App\Modules\Marketplace\Infrastructure;

use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Infrastructure\Coverage\PostgisLocalZoneSource;
use App\Modules\Marketplace\Interfaces\Console\ProbeLocalCoverage;
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
        if ($this->app->environment(['local', 'testing']) && $this->app->runningInConsole()) {
            $this->commands([ProbeLocalCoverage::class]);
        }
    }
}
