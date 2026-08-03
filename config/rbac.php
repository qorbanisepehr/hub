<?php

return [
    'super_admin_email' => env('SUPER_ADMIN_EMAIL'),
    'cache_store' => env('RBAC_CACHE_STORE', 'array'),
    'matrix_manager_types' => [
        'project' => 'مدیر پروژه',
        'functional' => 'مدیر عملکردی',
        'technical' => 'مدیر فنی',
    ],
];
