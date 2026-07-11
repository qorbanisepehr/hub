<?php

return [
    'storage_disk' => env('DOCUMENT_STORAGE_DISK', 'local'),

    'max_file_size' => env('DOCUMENT_MAX_FILE_SIZE', 50 * 1024),

    'allowed_mime_types' => [
        'pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'txt', 'csv',
    ],
];
