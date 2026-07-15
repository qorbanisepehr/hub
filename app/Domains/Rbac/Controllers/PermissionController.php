<?php

namespace App\Domains\Rbac\Controllers;

use App\Domains\Rbac\Models\Permission;
use App\Domains\Rbac\Models\PermissionGroup;
use App\Domains\Rbac\Requests\StorePermissionRequest;
use App\Domains\Rbac\Resources\PermissionGroupResource;
use App\Domains\Rbac\Resources\PermissionResource;
use App\Domains\Rbac\Services\PermissionRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController
{
    public function index(): AnonymousResourceCollection
    {
        $groups = PermissionGroup::with('permissions')->orderBy('sort_order')->get();

        return PermissionGroupResource::collection($groups);
    }

    public function registered(): JsonResponse
    {
        return response()->json(PermissionRegistrar::getRegisteredGroups());
    }

    public function store(StorePermissionRequest $request): PermissionResource
    {
        $permission = Permission::create($request->validated());

        return new PermissionResource($permission);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->roles()->detach();
        $permission->delete();

        return response()->json(['message' => 'Permission deleted']);
    }
}
