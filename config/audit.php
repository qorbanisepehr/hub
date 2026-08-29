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

    /*
    |--------------------------------------------------------------------------
    | Event Catalog
    |--------------------------------------------------------------------------
    |
    | Registry of every auditable event (v6 PHASE 6): its category and
    | sensitivity level (low | medium | high). The AuditEventCatalogTest
    | enforces that this catalog and the domain Events directories stay in
    | sync in both directions, so a new event cannot ship unregistered.
    |
    */

    'event_catalog' => [
        // Audit lifecycle & retention
        'audit.lifecycle.executed' => ['category' => 'audit', 'sensitivity' => 'low'],
        'audit.retention.created' => ['category' => 'audit', 'sensitivity' => 'medium'],
        'audit.retention.updated' => ['category' => 'audit', 'sensitivity' => 'medium'],
        'audit.retention.deleted' => ['category' => 'audit', 'sensitivity' => 'medium'],

        // Authentication
        'auth.login.success' => ['category' => 'auth', 'sensitivity' => 'low'],
        'auth.login.failed' => ['category' => 'auth', 'sensitivity' => 'medium'],
        'auth.logout.success' => ['category' => 'auth', 'sensitivity' => 'low'],
        'auth.password.changed' => ['category' => 'auth', 'sensitivity' => 'high'],
        'auth.profile.updated' => ['category' => 'auth', 'sensitivity' => 'medium'],

        // Authorization
        'authorization.active_role.changed' => ['category' => 'authorization', 'sensitivity' => 'medium'],
        'authorization.permission.assigned' => ['category' => 'authorization', 'sensitivity' => 'high'],
        'authorization.role.assigned' => ['category' => 'authorization', 'sensitivity' => 'low'],
        'authorization.role.created' => ['category' => 'authorization', 'sensitivity' => 'low'],
        'authorization.role.deleted' => ['category' => 'authorization', 'sensitivity' => 'medium'],
        'authorization.role.removed' => ['category' => 'authorization', 'sensitivity' => 'low'],
        'authorization.role.toggled' => ['category' => 'authorization', 'sensitivity' => 'low'],
        'authorization.role.updated' => ['category' => 'authorization', 'sensitivity' => 'low'],
        'authorization.user.created' => ['category' => 'authorization', 'sensitivity' => 'medium'],
        'authorization.user.updated' => ['category' => 'authorization', 'sensitivity' => 'medium'],

        // Documents
        'document.deleted' => ['category' => 'document', 'sensitivity' => 'medium'],
        'document.downloaded' => ['category' => 'document', 'sensitivity' => 'high'],
        'document.force_deleted' => ['category' => 'document', 'sensitivity' => 'high'],
        'document.placed' => ['category' => 'document', 'sensitivity' => 'medium'],
        'document.restored' => ['category' => 'document', 'sensitivity' => 'medium'],
        'document.uploaded' => ['category' => 'document', 'sensitivity' => 'medium'],

        // Employees
        'employee.created' => ['category' => 'employee', 'sensitivity' => 'medium'],
        'employee.deleted' => ['category' => 'employee', 'sensitivity' => 'high'],
        'employee.submitted' => ['category' => 'employee', 'sensitivity' => 'medium'],
        'employee.updated' => ['category' => 'employee', 'sensitivity' => 'high'],

        // Questionnaires
        'questionnaire.rejected' => ['category' => 'questionnaire', 'sensitivity' => 'medium'],
        'questionnaire.reviewed' => ['category' => 'questionnaire', 'sensitivity' => 'medium'],
        'questionnaire.submitted' => ['category' => 'questionnaire', 'sensitivity' => 'medium'],
    ],

];
