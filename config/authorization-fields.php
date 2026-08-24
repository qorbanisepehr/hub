<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Field Groups
    |--------------------------------------------------------------------------
    |
    | Field groups map the keys a resource emits to the permission that gates
    | them. Field authorization is deny-based: a group is visible whenever the
    | actor may view the resource, and is stripped from the API response only
    | when the actor's active role chain carries an explicit DENY rule for the
    | group's `.view` permission (see FieldAccess).
    |
    |   label      – human readable name (for the future Policy Builder UI)
    |   permission – the permission key evaluated for the group
    |   fields     – response keys removed when the group is denied
    |
    | Only groups backed by real data are registered. `employee.salary` will
    | land here once salary data exists.
    |
    */

    'groups' => [

        'employee' => [
            'personal_info' => [
                'label' => 'اطلاعات شخصی',
                'permission' => 'employee.personal_info.view',
                'fields' => [
                    'first_name',
                    'last_name',
                    'gender',
                    'birth_date',
                    'id_number',
                    'marital_status',
                    'section_personal',
                    'section_dependents',
                ],
            ],
            'employment_info' => [
                'label' => 'اطلاعات استخدام',
                'permission' => 'employee.employment_info.view',
                'fields' => [
                    'personnel_code',
                    'employment_type',
                    'hire_date',
                    'employment_status',
                ],
            ],
        ],

    ],

];
