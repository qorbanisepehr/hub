<?php

namespace App\Domains\Employee\Controllers;

use App\Contracts\Authorization;
use App\Domains\Employee\Events\EmployeeCreated;
use App\Domains\Employee\Events\EmployeeDeleted;
use App\Domains\Employee\Events\EmployeeSubmitted;
use App\Domains\Employee\Events\EmployeeUpdated;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Requests\SaveEmployeeSectionRequest;
use App\Domains\Employee\Requests\StoreEmployeeRequest;
use App\Domains\Employee\Requests\SubmitEmployeeRequest;
use App\Domains\Employee\Requests\UpdateEmployeeRequest;
use App\Domains\Employee\Resources\EmployeeResource;
use App\Domains\Employee\Services\EmployeeService;
use App\Http\Controllers\ApiController;
use App\Support\ListQuery;
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

        if ($filter = ListQuery::filter($request)) {
            $query->where(function ($q) use ($filter) {
                $q->where('personnel_code', 'like', "%{$filter}%")
                    ->orWhere('first_name', 'like', "%{$filter}%")
                    ->orWhere('last_name', 'like', "%{$filter}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('employment_status', $request->input('status'));
        }

        $sortField = ListQuery::sort($request, default: 'personnel_code');
        $sortDirection = ListQuery::order($request);
        $query->orderBy($this->sortable[$sortField] ?? 'created_at', $sortDirection);

        $employees = $query->paginate(ListQuery::perPage($request));

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): EmployeeResource
    {
        $employee = $this->employeeService->create(
            $request->validated(),
            $this->collectSections($request),
        );
        $employee->load(['user']);

        event(new EmployeeCreated($employee));

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

        $oldValues = $employee->only(array_keys($request->validated()));
        $employee->update($request->validated());
        $newValues = $employee->only(array_keys($request->validated()));
        $employee->load(['user']);

        $actualChanges = $this->diffAttributes($oldValues, $newValues);

        if ($actualChanges !== []) {
            event(new EmployeeUpdated(
                $employee,
                array_intersect_key($oldValues, $actualChanges),
                array_intersect_key($newValues, $actualChanges),
            ));
        }

        return new EmployeeResource($employee);
    }

    public function saveSection(Employee $employee, string $section, SaveEmployeeSectionRequest $request): EmployeeResource
    {
        $actor = $request->user();
        $authorization = app(Authorization::class);
        // OR semantics: the section's own save permission is sufficient on
        // its own, and the generic update permission keeps working.
        if (! $authorization->can($actor, 'employee.update', $employee)
            && ! $authorization->can($actor, $this->employeeService->savePermissionFor($section), $employee)) {
            abort(403, __('messages.permission_denied'));
        }

        $oldValues = $employee->toArray();

        $employee = $this->employeeService->saveSection($employee, $section, $request->validated(), $request->user());

        $newValues = $employee->toArray();
        $employee->load(['user']);

        $actualChanges = $this->diffAttributes($oldValues, $newValues);

        if ($actualChanges !== []) {
            event(new EmployeeUpdated(
                $employee,
                array_intersect_key($oldValues, $actualChanges),
                array_intersect_key($newValues, $actualChanges),
                $section,
            ));
        }

        return new EmployeeResource($employee);
    }

    public function submit(Employee $employee, SubmitEmployeeRequest $request): EmployeeResource
    {
        $this->authorization->authorize($request->user(), 'employee.update', $employee);

        $employee = $this->employeeService->submit($employee);
        $employee->load(['user']);

        event(new EmployeeSubmitted($employee));

        return new EmployeeResource($employee);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'employee.delete', $employee);

        $employeeId = $employee->getKey();
        $employee->delete();

        event(new EmployeeDeleted($employeeId));

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

    /**
     * Compare two attribute arrays and return only keys where values actually differ.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private function diffAttributes(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old) || $old[$key] !== $value) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }
}
