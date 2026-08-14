<?php

namespace App\Domains\Authorization\Controllers;

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Requests\StorePermissionRequest;
use App\Domains\Authorization\Resources\PermissionGroupResource;
use App\Domains\Authorization\Resources\PermissionResource;
use App\Domains\Authorization\Services\PermissionRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController
{
    public function index(): AnonymousResourceCollection
    {
        $groups = PermissionGroup::with('permissions')->orderBy('sort_order')->get();

        return PermissionGroupResource::collection($groups);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $query = Permission::with('group');

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            $query->where(function ($q) use ($filter) {
                $q->where('name', 'like', "%{$filter}%")
                    ->orWhere('display_name', 'like', "%{$filter}%");
            });
        }

        $query->orderBy('name', 'asc');

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);
        $permissions = $query->paginate($perPage);

        return PermissionResource::collection($permissions);
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

        return response()->json(['message' => __('authorization.permission_deleted')]);
    }
}
