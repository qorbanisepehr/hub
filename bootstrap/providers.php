<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuditServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PermissionServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    AuditServiceProvider::class,
    NotificationServiceProvider::class,
    RateLimitServiceProvider::class,
    PermissionServiceProvider::class,
];
