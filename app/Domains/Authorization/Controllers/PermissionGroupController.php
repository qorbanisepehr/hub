<?php

namespace App\Domains\Authorization\Controllers;

use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Requests\StorePermissionGroupRequest;
use App\Domains\Authorization\Requests\UpdatePermissionGroupRequest;
use App\Domains\Authorization\Resources\PermissionGroupResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionGroupController
{
    public function index(): AnonymousResourceCollection
    {
        $groups = PermissionGroup::with('permissions')->orderBy('sort_order')->get();

        return PermissionGroupResource::collection($groups);
    }

    public function store(StorePermissionGroupRequest $request): PermissionGroupResource
    {
        $group = PermissionGroup::create($request->validated());

        return new PermissionGroupResource($group);
    }

    public function update(UpdatePermissionGroupRequest $request, PermissionGroup $group): PermissionGroupResource
    {
        $group->update($request->validated());

        return new PermissionGroupResource($group);
    }

    public function destroy(PermissionGroup $group): JsonResponse
    {
        $group->delete();

        return response()->json(['message' => __('authorization.permission_group_deleted')]);
    }
}
