<?php

namespace App\Domains\Authorization\Controllers;

use App\Domains\Auth\Resources\UserResource;
use App\Domains\Authorization\Requests\StoreUserRequest;
use App\Domains\Authorization\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController
{
    /** @var array<string, string> */
    private array $sortable = [
        'name' => 'name',
        'email' => 'email',
        'created_at' => 'created_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::with(['roles', 'activeRole']);

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            $query->where(function ($q) use ($filter) {
                $q->where('name', 'like', "%{$filter}%")
                    ->orWhere('email', 'like', "%{$filter}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('roles.id', $request->input('role'));
            });
        }

        if ($request->has('has_employee')) {
            $hasEmployee = filter_var($request->input('has_employee'), FILTER_VALIDATE_BOOLEAN);
            if (! $hasEmployee) {
                $query->doesntHave('employee');
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('order', 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($this->sortable[$sortField] ?? 'name', $sortDirection);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $users = $query->paginate($perPage);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        $user->load(['roles', 'activeRole']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function show(User $user): UserResource
    {
        $user->load(['roles', 'activeRole']);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            unset($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        $user->load(['roles', 'activeRole']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
