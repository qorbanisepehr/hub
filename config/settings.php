<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings Storage
    |--------------------------------------------------------------------------
    |
    | Settings are persisted as one small JSON file per key under the storage
    | path below (see FileSettingsRepository). The path is configurable so it
    | can be pointed elsewhere (e.g. a shared volume) if needed.
    |
    */

    'storage_path' => storage_path('app/settings'),

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | The branding key holds name / sub_name / logo & logotype files. Files are
    | stored on the dedicated "branding" disk and served with immutable cache
    | headers, versioned by file mtime.
    |
    */

    'branding' => [
        'key' => 'branding',

        'disk' => env('BRANDING_DISK', 'branding'),

        'logo' => [
            'filename' => 'logo',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
            'max_size_kb' => 2048,
        ],

        'logotype' => [
            'filename' => 'logotype',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
            'max_size_kb' => 2048,
        ],

        'favicon' => [
            'filename' => 'favicon',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg', 'ico'],
            'max_size_kb' => 2048,
        ],

        'og_image' => [
            'filename' => 'og_image',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_size_kb' => 2048,
        ],
    ],

];
