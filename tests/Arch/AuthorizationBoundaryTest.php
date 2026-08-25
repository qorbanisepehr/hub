<?php

use App\Domains\Authorization\Attributes\AttributeRegistry;
use App\Domains\Authorization\Engine\AuthorizationEngine;
use App\Domains\Authorization\Models\AccessRule;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Policies\ConditionEvaluator;
use App\Domains\Authorization\Policies\Operator;
use App\Domains\Authorization\Policies\PolicyValidator;
use App\Domains\Authorization\Policies\QueryTranslator;
use App\Domains\Authorization\Services\AuthorizationService;
use App\Models\Traits\HasRoles;

arch('business domains never depend on the RBAC implementation')
    ->expect([
        'App\Domains\Document',
        'App\Domains\Employee',
        'App\Domains\Cv',
        'App\Domains\Questionnaire',
    ])
    ->not->toUse([
        AccessRule::class,
        Permission::class,
        PermissionGroup::class,
        Role::class,
        AuthorizationEngine::class,
        AuthorizationService::class,
        ConditionEvaluator::class,
        Operator::class,
        PolicyValidator::class,
        QueryTranslator::class,
        AttributeRegistry::class,
        HasRoles::class,
    ]);
