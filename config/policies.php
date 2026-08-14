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
            'viewAny' => '{group}.list',
            'view' => '{group}.view',
            'create' => '{group}.create',
            'update' => '{group}.update',
            'delete' => '{group}.delete',
            'scopeOwn' => '{group}.view',
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
                'viewAny' => 'employee.documents.view',
                'view' => 'employee.documents.view',
                'create' => 'employee.documents.upload',
                'download' => 'employee.documents.download',
                'update' => 'employee.documents.upload',
                'delete' => 'employee.documents.delete',
                'scopeOwn' => 'employee.documents.view',
            ],
        ],

    ],

];
