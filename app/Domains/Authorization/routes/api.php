<?php

use App\Domains\Authorization\Controllers\AuthorizationCheckController;
use App\Domains\Authorization\Controllers\AuthorizationExplainController;
use App\Domains\Authorization\Controllers\PermissionController;
use App\Domains\Authorization\Controllers\PermissionGroupController;
use App\Domains\Authorization\Controllers\RoleController;
use App\Domains\Authorization\Controllers\UserController;
use App\Domains\Authorization\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('authorization/explain', AuthorizationExplainController::class)
        ->middleware('permission:role.view');
    Route::post('authorization/check', AuthorizationCheckController::class);
    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:role.view');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('permission:role.create');
    Route::get('roles/chart', [RoleController::class, 'chart'])
        ->middleware('permission:role.view');
    Route::get('roles/{role}', [RoleController::class, 'show'])
        ->middleware('permission:role.view');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:role.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:role.delete');
    Route::patch('roles/{role}/toggle', [RoleController::class, 'toggle'])
        ->middleware('permission:role.update');
    Route::post('roles/batch-assign-permissions', [RoleController::class, 'batchAssignPermissions'])
        ->middleware('permission:role.update');

    Route::get('permissions', [PermissionController::class, 'index'])
        ->middleware('permission:role.view');
    Route::get('permissions/search', [PermissionController::class, 'search'])
        ->middleware('permission:role.view');
    Route::get('permissions/registered', [PermissionController::class, 'registered'])
        ->middleware('permission:role.view');
    Route::post('permissions', [PermissionController::class, 'store'])
        ->middleware('permission:role.create');
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])
        ->middleware('permission:role.delete');

    Route::get('permission-groups', [PermissionGroupController::class, 'index'])
        ->middleware('permission:role.view');
    Route::post('permission-groups', [PermissionGroupController::class, 'store'])
        ->middleware('permission:role.create');
    Route::put('permission-groups/{group}', [PermissionGroupController::class, 'update'])
        ->middleware('permission:role.update');
    Route::delete('permission-groups/{group}', [PermissionGroupController::class, 'destroy'])
        ->middleware('permission:role.delete');

    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:user.view');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware('permission:user.view');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('permission:user.update');

    Route::get('users/{user}/roles', [UserRoleController::class, 'index'])
        ->middleware('permission:user.view');
    Route::post('users/{user}/roles', [UserRoleController::class, 'store'])
        ->middleware('permission:user.assign-roles');
    Route::delete('users/{user}/roles/{role}', [UserRoleController::class, 'destroy'])
        ->middleware('permission:user.assign-roles');
    Route::post('users/{user}/switch-active-role', [UserRoleController::class, 'switchActive'])
        ->middleware('permission:user.assign-roles');

    Route::get('roles/chart/export', [RoleController::class, 'exportChart'])
        ->middleware('permission:role.view');

    Route::get('roles/chart/export-fields', [RoleController::class, 'exportFields'])
        ->middleware('permission:role.view');
});
