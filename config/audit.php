<?php

use App\Domains\Audit\Services\NullArchiveStore;

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
    | matches. Records older than this will be pruned by audit:retention.
    |
    */

    'default_retention_days' => 365,

    /*
    |--------------------------------------------------------------------------
    | Archive Store
    |--------------------------------------------------------------------------
    |
    | Strategy used to move expired records to long-term storage before they
    | are pruned. V1 ships a no-op store; swap in a database/file/S3
    | implementation without touching lifecycle logic.
    |
    */

    'archive_store' => NullArchiveStore::class,

];
