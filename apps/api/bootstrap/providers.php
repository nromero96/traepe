<?php

use App\Modules\Identity\Infrastructure\IdentityServiceProvider;
use App\Modules\Platform\Infrastructure\PlatformServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
];
