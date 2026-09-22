<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External Integrations
    |--------------------------------------------------------------------------
    |
    | Connection settings for external systems. Secrets stay in .env / the
    | secret store — never in code (plan §66). Each integration owns its
    | block; `driver` selects which implementation the container binds for
    | the system's capability gateways.
    |
    */

    'rahkaran' => [
        // 'fake' serves deterministic sample data through the real mapper
        // until the real connection details (SQL Server, API, ...) are known.
        'driver' => env('RAHKARAN_DRIVER', 'fake'),

        'base_url' => env('RAHKARAN_BASE_URL'),

        // Request timeout in seconds; owned by the integration, not reports.
        'timeout' => (int) env('RAHKARAN_TIMEOUT', 10),

        // Wall-clock timezone the source stores its times in.
        'timezone' => env('RAHKARAN_TIMEZONE', 'Asia/Tehran'),
    ],

];
