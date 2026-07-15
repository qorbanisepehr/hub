<?php

namespace App\Domains\Employee\Controllers;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Requests\StoreEmployeeRequest;
use App\Domains\Employee\Requests\UpdateEmployeeRequest;
use App\Domains\Employee\Resources\EmployeeResource;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends ApiController
{
    protected ?string $model = Employee::class;

    /** @var array<string, string> */
    private array $sortable = [
        'personnel_code' => 'personnel_code',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'gender' => 'gender',
        'employment_status' => 'employment_status',
        'hire_date' => 'hire_date',
        'created_at' => 'created_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::with(['user']);

        $this->scopeQuery($query, $request, 'user_id');

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            $query->where(function ($q) use ($filter) {
                $q->where('personnel_code', 'like', "%{$filter}%")
                    ->orWhere('first_name', 'like', "%{$filter}%")
                    ->orWhere('last_name', 'like', "%{$filter}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('employment_status', $request->input('status'));
        }

        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($this->sortable[$sortField] ?? 'created_at', $sortDirection);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $employees = $query->paginate($perPage);

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): EmployeeResource
    {
        $employee = Employee::create($request->validated());
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->update($request->validated());

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json(['message' => __('employee.deleted')]);
    }
}
