<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route-Level Rate Limiters
    |--------------------------------------------------------------------------
    |
    | These are applied via the "throttle" middleware on routes.
    | Each entry defines the maximum attempts and the decay window in seconds.
    |
    */

    'auth-login' => [
        'limit' => (int) env('AUTH_LOGIN_LIMIT', 5),
        'period' => (int) env('AUTH_LOGIN_PERIOD', 60),
    ],

    'auth-verify-otp' => [
        'limit' => (int) env('AUTH_VERIFY_OTP_LIMIT', 10),
        'period' => (int) env('AUTH_VERIFY_OTP_PERIOD', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application-Level Rate Limiter
    |--------------------------------------------------------------------------
    |
    | This controls the per-user lockout inside the controller.
    | Failed OTP verifications and password attempts share this counter.
    |
    */

    'auth-attempts' => [
        'limit' => (int) env('AUTH_ATTEMPTS_LIMIT', 5),
        'period' => (int) env('AUTH_ATTEMPTS_PERIOD', 60),
    ],
];
