<?php

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;

return [
    'storage_disk' => env('DOCUMENT_STORAGE_DISK', 'local'),

    'max_file_size' => env('DOCUMENT_MAX_FILE_SIZE', 50 * 1024 * 1024),

    'allowed_mime_types' => [
        'pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'txt', 'csv',
    ],

    'questionnaire' => [
        'max_file_size' => env('QUESTIONNAIRE_DOC_MAX_SIZE', 10 * 1024 * 1024),
        'max_files' => env('QUESTIONNAIRE_DOC_MAX_FILES', 20),
        'allowed_mime_types' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    'cv' => [
        'max_file_size' => env('CV_DOC_MAX_SIZE', 10 * 1024 * 1024),
        'max_files' => env('CV_DOC_MAX_FILES', 20),
        'allowed_mime_types' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    /**
     * Documentable entity type → SectionRegistry class. Lets the document
     * domain resolve placement labels through the owning domain's sections
     * without importing it. New domains opt in with one line (OCP).
     */
    'section_registries' => [
        Employee::class => EmployeeService::class,
    ],

    'employee' => [
        'max_file_size' => env('EMPLOYEE_DOC_MAX_SIZE', 10 * 1024 * 1024),
        // Raised for dependents documents: each dependent may carry up to 7
        // identity pages (national-card x2 + birth-certificate x5) on top of
        // the employee's own documents. Override per-environment via env.
        'max_files' => env('EMPLOYEE_DOC_MAX_FILES', 60),
        'allowed_mime_types' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    'thumbnail' => [
        'max_width' => 300,
        'max_height' => 300,
        'quality' => 80,
    ],
];
