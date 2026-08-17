<?php

namespace App\Domains\Employee\Controllers;

use App\Contracts\Authorization;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Requests\SaveEmployeeSectionRequest;
use App\Domains\Employee\Requests\StoreEmployeeRequest;
use App\Domains\Employee\Requests\SubmitEmployeeRequest;
use App\Domains\Employee\Requests\UpdateEmployeeRequest;
use App\Domains\Employee\Resources\EmployeeResource;
use App\Domains\Employee\Services\EmployeeService;
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

    public function __construct(
        private EmployeeService $employeeService,
        private Authorization $authorization,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::with(['user']);

        $this->authorization->scope($request->user(), 'employee.list', $query);

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

        $sortField = $request->input('sort', 'personnel_code');
        $sortDirection = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($this->sortable[$sortField] ?? 'created_at', $sortDirection);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $employees = $query->paginate($perPage);

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): EmployeeResource
    {
        $employee = $this->employeeService->create(
            $request->validated(),
            $this->collectSections($request),
        );
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function show(Request $request, Employee $employee): EmployeeResource
    {
        $this->authorization->authorize($request->user(), 'employee.view', $employee);

        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->authorization->authorize($request->user(), 'employee.update', $employee);

        $employee->update($request->validated());
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function saveSection(Employee $employee, string $section, SaveEmployeeSectionRequest $request): EmployeeResource
    {
        $this->authorization->authorize($request->user(), 'employee.update', $employee);

        $employee = $this->employeeService->saveSection($employee, $section, $request->validated());
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function submit(Employee $employee, SubmitEmployeeRequest $request): EmployeeResource
    {
        $this->authorization->authorize($request->user(), 'employee.update', $employee);

        $employee = $this->employeeService->submit($employee);
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'employee.delete', $employee);

        $employee->delete();

        return response()->json(['message' => __('employee.deleted')]);
    }

    /**
     * Pull the submitted sections out of the request so the service can persist
     * them with their structural validation (same flow as the questionnaire).
     *
     * @return array<string, mixed>
     */
    private function collectSections(Request $request): array
    {
        $sections = [];

        foreach ($this->employeeService->getSectionKeys() as $key) {
            if ($request->has($key)) {
                $sections[$key] = $request->input($key);
            }
        }

        return $sections;
    }
}
