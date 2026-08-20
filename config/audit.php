<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Audit Domain. Controls sanitization, retention,
    | and other audit-specific behavior.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be redacted before storage. The sanitizer will replace
    | values in these fields with "[REDACTED]".
    |
    */

    'sensitive_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'otp',
        'token',
        'secret',
        'api_key',
        'api_secret',
        'ssn',
        'national_id',
        'id_number',
        'bank_account',
        'credit_card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Retention
    |--------------------------------------------------------------------------
    |
    | Default number of days to retain audit records when no specific policy
    | matches. Records older than this will be pruned by audit:prune.
    |
    */

    'default_retention_days' => 365,

    /*
    |--------------------------------------------------------------------------
    | Non-Auditable Events
    |--------------------------------------------------------------------------
    |
    | Events that should never be recorded. Prevents recursive logging and
    | noise from high-frequency, low-value events.
    |
    */

    'non_auditable_events' => [
        'audit.event.recorded',
        'audit.retention.pruned',
        'audit.retention.archived',
    ],

];
