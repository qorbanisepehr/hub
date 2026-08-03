<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    |
    | Central configuration for the unified OTP service. Route-level rate
    | limiters (auth-login, recruitment-otp-send, ...) remain in
    | config/rate-limits.php.
    |
    */

    // Number of digits in the generated code.
    'code_length' => (int) env('OTP_CODE_LENGTH', 6),

    // How long a code stays valid, in seconds.
    'ttl' => (int) env('OTP_TTL', 120),

    // Per-entity failed-attempt lockout (the route limiters guard IPs).
    'attempts' => [
        'limit' => (int) env('OTP_ATTEMPTS_LIMIT', 5),
        'period' => (int) env('OTP_ATTEMPTS_PERIOD', 300),
    ],

    // Short-lived access grants issued after an access-protected verification.
    'grants' => [
        'ttl' => (int) env('OTP_GRANTS_TTL', 600),

        // Per-purpose lifetime in seconds. Any purpose not listed falls back
        // to the default `ttl` above.
        'purpose_ttl' => [
            'view' => (int) env('OTP_GRANTS_TTL_VIEW', 1800),
            'edit' => (int) env('OTP_GRANTS_TTL_EDIT', 3600),
        ],
    ],
];
