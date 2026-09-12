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
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController
{
    private const SORTABLE = [
        'personnel_code',
        'first_name',
        'full_name',
        'last_name',
        'gender',
        'employment_status',
        'hire_date',
        'created_at',
    ];

    private const SEARCHABLE = [
        'personnel_code',
        'first_name',
        'last_name',
    ];

    public function __construct(
        private EmployeeService $employeeService,
        private Authorization $authorization,
    ) {}

    /**
     * The UI sorts the combined name column under one id; the database sorts
     * by the leading first name.
     */
    private function sortColumn(string $id): string
    {
        return $id === 'full_name' ? 'first_name' : $id;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::with(['user.activeRole']);

        $this->authorization->scope($request->user(), 'employee.list', $query);

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        if ($request->filled('status')) {
            $query->where('employment_status', $request->input('status'));
        }

        if ($request->filled('status_not')) {
            $query->where('employment_status', '!=', $request->input('status_not'));
        }

        $query->orderBy(
            $this->sortColumn(ListQuery::sort($request, self::SORTABLE, 'personnel_code')),
            ListQuery::order($request),
        );

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

        $this->employeeService->update($employee, $request->validated());
        $employee->load(['user']);

        return new EmployeeResource($employee);
    }

    public function saveSection(Employee $employee, string $section, SaveEmployeeSectionRequest $request): EmployeeResource
    {
        $actor = $request->user();
        // OR semantics: the section's own save permission is sufficient on
        // its own, and the generic update permission keeps working.
        if (! $this->authorization->can($actor, 'employee.update', $employee)
            && ! $this->authorization->can($actor, $this->employeeService->savePermissionFor($section), $employee)) {
            abort(403, __('messages.permission_denied'));
        }

        $employee = $this->employeeService->saveSection($employee, $section, $request->validated(), $request->user());
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

        $this->employeeService->delete($employee);

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
