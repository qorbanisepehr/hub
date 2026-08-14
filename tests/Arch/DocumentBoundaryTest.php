<?php

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Cv\Repositories\CvRepository;
use App\Domains\Cv\Services\CvService;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepository;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use App\Models\Traits\HasRoles;

arch('document domain never depends on other domains services or repositories')
    ->expect('App\Domains\Document')
    ->not->toUse([
        CvService::class,
        CvRepository::class,
        EmployeeService::class,
        QuestionnaireService::class,
        QuestionnaireRepository::class,
    ]);

arch('document domain must not know the RBAC implementation')
    ->expect('App\Domains\Document')
    ->not->toUse([
        Permission::class,
        PermissionGroup::class,
        Role::class,
        HasRoles::class,
    ]);
