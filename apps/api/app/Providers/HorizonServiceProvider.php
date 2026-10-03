<?php

namespace App\Providers;

use App\Infrastructure\TechnicalAccess;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    protected function authorization(): void
    {
        // The local environment alone never grants access.
        Horizon::auth(fn ($request): bool => app(TechnicalAccess::class)->allows($request));
    }
}
