<?php

use App\Http\Controllers\TechnicalBroadcastAuthController;
use App\Http\Middleware\TechnicalAuthentication;
use App\Modules\Platform\Interfaces\Http\ReadinessController;
use Illuminate\Support\Facades\Route;

// Pusher/Reverb requires its native signing response (without a resource envelope).
Route::prefix('api/v1')->group(function (): void {
    Route::post('technical/broadcasting/auth', TechnicalBroadcastAuthController::class)
        ->middleware([TechnicalAuthentication::class, 'throttle:30,1']);

    Route::get('health/ready', ReadinessController::class)->middleware('api');
});
