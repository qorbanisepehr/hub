<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuditServiceProvider;
use App\Providers\CoreRepositoryServiceProvider;
use App\Providers\ExportKernelServiceProvider;
use App\Providers\ExternalGatewayServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PermissionServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    ExternalGatewayServiceProvider::class,
    CoreRepositoryServiceProvider::class,
    ExportKernelServiceProvider::class,
    AppServiceProvider::class,
    AuditServiceProvider::class,
    NotificationServiceProvider::class,
    PermissionServiceProvider::class,
    RateLimitServiceProvider::class,
];
