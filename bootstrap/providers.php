<?php

use App\Providers\AppServiceProvider;
use App\Providers\PermissionServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    RateLimitServiceProvider::class,
    PermissionServiceProvider::class,
];
