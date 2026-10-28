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

    'farzin' => [
        // 'fake' serves deterministic sample rows through the real
        // normalize → ingest → registry pipeline; 'sqlsrv' activates the
        // real read-only connection. Credentials live in .env under the
        // `sqlsrv` database connection, never in code.
        'driver' => env('FARZIN_DRIVER', 'fake'),

        // Roles whose A4 letters file board invitations. The secretariat
        // is the only one today (TCK parity); adding a role is config.
        'letter_creator_role_ids' => array_filter(array_map(
            'intval',
            explode(',', (string) env('FARZIN_LETTER_CREATOR_ROLE_IDS', '2880')),
        )),

        // An incremental sync reads rows edited after this many days ago
        // on the FIRST run, when no sync state exists yet (safety window).
        'initial_lookback_days' => (int) env('FARZIN_INITIAL_LOOKBACK_DAYS', 30),
    ],

];
