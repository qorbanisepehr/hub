<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuditServiceProvider;
use App\Providers\PermissionServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    AuditServiceProvider::class,
    RateLimitServiceProvider::class,
    PermissionServiceProvider::class,
];
