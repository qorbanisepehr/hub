<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Groups
    |--------------------------------------------------------------------------
    |
    | Define all permission groups and their permissions here.
    | Each group has a slug, display name, and array of permissions.
    | Permission names follow the convention: {group}.{action}
    |
    | To add a new entity, simply add a new group array below.
    |
    */

    'groups' => [

        'user' => [
            'name' => 'User Management',
            'permissions' => [
                'user.view' => 'View user list',
                'user.create' => 'Create users',
                'user.update' => 'Edit users',
                'user.assign-roles' => 'Assign/remove roles',
                'user.delete' => 'Delete users',
            ],
        ],

        'role' => [
            'name' => 'Role Management',
            'permissions' => [
                'role.view' => 'View roles',
                'role.create' => 'Create roles',
                'role.update' => 'Edit roles',
                'role.delete' => 'Delete roles',
            ],
        ],

        'employee' => [
            'name' => 'Employee Profile',
            'permissions' => [
                'employee.list' => 'List employees',
                'employee.view' => 'View an employee profile',
                'employee.create' => 'Create new employees',
                'employee.update' => 'Update employee profiles',
                'employee.delete' => 'Delete employees',
                'employee.personal_info.view' => 'View employee personal information fields',
                'employee.employment_info.view' => 'View employee employment information fields',
            ],
        ],

        'employee.documents' => [
            'name' => 'Employee Documents',
            'permissions' => [
                'employee.documents.view' => "View an employee's documents",
                'employee.documents.upload' => 'Upload documents',
                'employee.documents.replace' => 'Replace documents',
                'employee.documents.download' => 'Download documents',
                'employee.documents.delete' => 'Delete documents',
                'employee.documents.restore' => 'Restore documents from the trash',
                'employee.documents.force-delete' => 'Permanently delete documents',
                'employee.documents.library-select' => 'Select documents from the library',
                'employee.documents.history-view' => 'View document history',
                'employee.documents.history-download' => 'Download documents from history',
            ],
        ],

        'cv.documents' => [
            'name' => 'CV Documents',
            'permissions' => [
                'cv.documents.view' => "View a CV's documents",
                'cv.documents.upload' => 'Upload documents',
                'cv.documents.download' => 'Download documents',
                'cv.documents.delete' => 'Delete documents',
                'cv.documents.history-view' => 'View document history',
                'cv.documents.history-download' => 'Download documents from history',
            ],
        ],

        'questionnaire.documents' => [
            'name' => 'Questionnaire Documents',
            'permissions' => [
                'questionnaire.documents.view' => "View a questionnaire's documents",
                'questionnaire.documents.upload' => 'Upload documents',
                'questionnaire.documents.download' => 'Download documents',
                'questionnaire.documents.delete' => 'Delete documents',
                'questionnaire.documents.history-view' => 'View document history',
                'questionnaire.documents.history-download' => 'Download documents from history',
            ],
        ],

        'document-category' => [
            'name' => 'Document Categories',
            'permissions' => [
                'document-category.view' => 'View categories',
                'document-category.manage' => 'Create/edit/delete categories',
            ],
        ],

        'bulk-import' => [
            'name' => 'Bulk Import',
            'permissions' => [
                'bulk-import.employee' => 'Import employees from Excel/CSV',
            ],
        ],

        'cv' => [
            'name' => 'CV Bank',
            'permissions' => [
                'cv.view' => 'View CV bank',
                'cv.approve' => 'Approve CVs',
                'cv.reject' => 'Reject CVs',
                'cv.create-questionnaire' => 'Create questionnaire from CV',
            ],
        ],

        'questionnaire' => [
            'name' => 'Questionnaire Bank',
            'permissions' => [
                'questionnaire.view' => 'View questionnaires',
                'questionnaire.review' => 'Review questionnaires',
                'questionnaire.reject' => 'Reject questionnaires',
            ],
        ],

        'branding' => [
            'name' => 'Branding',
            'permissions' => [
                'branding.view' => 'View branding',
                'branding.manage' => 'Manage branding',
            ],
        ],

        'form-options' => [
            'name' => 'Form Options',
            'permissions' => [
                'form-options.view' => 'View form options',
                'form-options.manage' => 'Manage form options',
            ],
        ],

    ],

];
