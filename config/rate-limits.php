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

    /*
    |--------------------------------------------------------------------------
    | Questionnaire OTP Rate Limiters
    |--------------------------------------------------------------------------
    |
    | Send: per-uuid + per-IP safety net for sending OTP (default: 5 per 120
    |       seconds). The app-level "already sent" cooldown (OtpService) is the
    |       primary guard; this limiter protects shared/nat IPs.
    | Verify: per-uuid + per-IP failed attempt tracking (default: 5 per 300
    |         seconds). The app-level lockout (OtpService) is the primary guard.
    |
    */

    'questionnaire-otp-send' => [
        'limit' => (int) env('QUESTIONNAIRE_OTP_SEND_LIMIT', 5),
        'period' => (int) env('QUESTIONNAIRE_OTP_SEND_PERIOD', 120),
        'ttl' => (int) env('QUESTIONNAIRE_OTP_SEND_TTL', 120),
    ],

    'questionnaire-otp-verify' => [
        'limit' => (int) env('QUESTIONNAIRE_OTP_VERIFY_LIMIT', 5),
        'period' => (int) env('QUESTIONNAIRE_OTP_VERIFY_PERIOD', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Rate Limiters
    |--------------------------------------------------------------------------
    |
    | Spend: every route that costs a model call, per user (default: 12 per
    |        minute). A person may legitimately start a summary, test a
    |        provider, and ask for a merge proposal in one sitting; past this it
    |        is a loop, and the bill is the hub's.
    | Read:  the model registry pickers (default: 120 per minute per user). They
    |        cost nothing at the gateway, so they get a wide bucket of their own
    |        rather than sharing the spend allowance.
    |
    */

    'ai-spend' => [
        'limit' => (int) env('AI_SPEND_LIMIT', 12),
        'period' => (int) env('AI_SPEND_PERIOD', 60),
    ],

    'ai-read' => [
        'limit' => (int) env('AI_READ_LIMIT', 120),
        'period' => (int) env('AI_READ_PERIOD', 60),
    ],
];
