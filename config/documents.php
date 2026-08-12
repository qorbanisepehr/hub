<?php

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

    'employee' => [
        'max_file_size' => env('EMPLOYEE_DOC_MAX_SIZE', 10 * 1024 * 1024),
        'max_files' => env('EMPLOYEE_DOC_MAX_FILES', 20),
        'allowed_mime_types' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    'thumbnail' => [
        'max_width' => 300,
        'max_height' => 300,
        'quality' => 80,
    ],
];
