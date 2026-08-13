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
                'employee.view_own' => 'View own profile',
                'employee.view_all' => 'View any employee profile',
                'employee.update_own' => 'Update own profile',
                'employee.update_all' => 'Update any employee profile',
                'employee.create' => 'Create new employees',
                'employee.delete' => 'Delete employees',
            ],
        ],

        'document' => [
            'name' => 'Documents',
            'permissions' => [
                'document.view_own' => 'View own documents',
                'document.view_all' => "View any employee's documents",
                'document.upload_own' => 'Upload to own profile',
                'document.upload_all' => 'Upload to any profile',
                'document.download_own' => 'Download own documents',
                'document.download_all' => 'Download any documents',
                'document.delete_own' => 'Delete own documents',
                'document.delete_all' => 'Delete any documents',
                'document.library-select' => 'Select documents from the library',
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
