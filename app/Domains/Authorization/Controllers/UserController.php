<?php

namespace App\Domains\Authorization\Controllers;

use App\Contracts\Authorization;
use App\Domains\Auth\Resources\UserResource;
use App\Domains\Authorization\Events\UserCreated;
use App\Domains\Authorization\Events\UserUpdated;
use App\Domains\Authorization\Requests\StoreUserRequest;
use App\Domains\Authorization\Requests\UpdateUserRequest;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController
{
    public function __construct(
        private Authorization $authorization,
    ) {}

    /** @var array<string, string> */
    private array $sortable = [
        'name' => 'name',
        'email' => 'email',
        'created_at' => 'created_at',
    ];

    private const EMPLOYEE_COLUMNS = 'employee:id,user_id,first_name,last_name,personnel_code';

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::with(['roles', 'activeRole', self::EMPLOYEE_COLUMNS]);

        $this->authorization->scope($request->user(), 'user.view', $query);

        if ($filter = ListQuery::filter($request)) {
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

        $sortField = ListQuery::sort($request, default: 'name');
        $sortDirection = ListQuery::order($request, default: 'asc');
        $query->orderBy($this->sortable[$sortField] ?? 'name', $sortDirection);

        $users = $query->paginate(ListQuery::perPage($request));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        $user->load(['roles', 'activeRole', self::EMPLOYEE_COLUMNS]);

        event(new UserCreated($user));

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function show(Request $request, User $user): UserResource
    {
        $this->authorization->authorize($request->user(), 'user.view', $user);

        $user->load(['roles', 'activeRole', self::EMPLOYEE_COLUMNS]);

        return new UserResource($user);
    }

    public function authorization(Request $request, User $user, Authorization $authorization): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'user.view', $user);

        return response()->json([
            'data' => $authorization->effectivePermissions($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'user.update', $user);

        $old = $user->only(['name', 'email', 'is_active']);
        $data = $request->validated();

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            unset($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        $new = $user->only(['name', 'email', 'is_active']);

        $user->load(['roles', 'activeRole', self::EMPLOYEE_COLUMNS]);

        event(new UserUpdated($user, $old, $new));

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
