<?php

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Authorization\AuthenticatedActorDirectory;
use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Application\LocalOtp;
use App\Modules\Identity\Infrastructure\Authorization\LocalProbeResourceResolver;
use App\Modules\Identity\Infrastructure\Authorization\PostgresAuthorizationDirectory;
use App\Modules\Identity\Interfaces\Console\ReadLocalOtp;
use App\Modules\Identity\Interfaces\Http\LocalAuthorizationProbeController;
use App\Modules\Identity\Interfaces\Http\LocalIdentityController;
use App\Modules\Identity\Interfaces\Http\LocalIdentityEnvironment;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LocalOtp::class, PostgresLocalOtp::class);
        $this->app->bind(AuthorizationDirectory::class, PostgresAuthorizationDirectory::class);
        $this->app->bind(AuthenticatedActorDirectory::class, PostgresAuthorizationDirectory::class);
        if ($this->app->environment(['local', 'testing'])) {
            $this->app->bind(ResourceContextResolver::class, LocalProbeResourceResolver::class);
        }
    }

    public function boot(): void
    {
        // Test consent and delivery are never a production authentication provider.
        if (! $this->app->environment(['local', 'testing'])) {
            return;
        }
        if ($this->app->runningInConsole()) {
            $this->commands([ReadLocalOtp::class]);
        }
        Route::prefix('api/v1/auth')->middleware(['web', 'throttle:30,1'])->group(function (): void {
            Route::post('otp/request', [LocalIdentityController::class, 'request']);
            Route::post('otp/verify', [LocalIdentityController::class, 'verify']);
            Route::post('logout', [LocalIdentityController::class, 'logout'])->middleware('auth:sanctum');
            Route::get('me', [LocalIdentityController::class, 'me'])->middleware('auth:sanctum');
        });
        Route::get('api/v1/identity/local-authorization-probe', LocalAuthorizationProbeController::class)
            ->middleware([LocalIdentityEnvironment::class, 'web', 'auth:sanctum', 'throttle:30,1']);
    }
}
