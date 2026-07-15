<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;

return [

    /*
    |--------------------------------------------------------------------------
    | General Policy Template
    |--------------------------------------------------------------------------
    |
    | Default permission patterns applied to any model not listed in 'models'.
    | Use {group} as placeholder for the resolved permission group name.
    |
    | Models inherit this automatically via convention: class basename → kebab case.
    | e.g., DocumentCategory → 'document-category', Employee → 'employee'
    |
    */

    'general' => [
        'owner_field' => 'user_id',
        'permissions' => [
            'viewAny' => ['{group}.view_own', '{group}.view_all'],
            'view' => ['own' => '{group}.view_own', 'all' => '{group}.view_all'],
            'create' => '{group}.create',
            'update' => ['own' => '{group}.update_own', 'all' => '{group}.update_all'],
            'delete' => '{group}.delete',
            'scopeOwn' => ['own' => '{group}.view_own', 'all' => '{group}.view_all'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Overrides
    |--------------------------------------------------------------------------
    |
    | Only add models here that DEVIATE from the general template.
    |   - Different owner field (or null for no ownership)
    |   - Non-standard permission patterns
    |   - Custom methods (upload, download, etc.)
    |
    */

    'models' => [

        DocumentCategory::class => [
            'owner_field' => null,
            'permissions' => [
                'viewAny' => 'document-category.view',
                'view' => 'document-category.view',
                'create' => 'document-category.manage',
                'update' => 'document-category.manage',
                'delete' => 'document-category.manage',
            ],
        ],

        Document::class => [
            'owner_field' => 'uploaded_by',
            'permissions' => [
                'viewAny' => ['document.view_own', 'document.view_all'],
                'view' => ['own' => 'document.view_own', 'all' => 'document.view_all'],
                'upload' => ['document.upload_own', 'document.upload_all'],
                'download' => ['own' => 'document.download_own', 'all' => 'document.download_all'],
                'delete' => ['own' => 'document.delete_own', 'all' => 'document.delete_all'],
                'scopeOwn' => ['own' => 'document.view_own', 'all' => 'document.view_all'],
            ],
        ],

    ],

];
