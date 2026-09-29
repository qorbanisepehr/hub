<?php

namespace App\Domains\Employee\Controllers;

use App\Contracts\Authorization;
use App\Domains\Authorization\Services\FieldAccess;
use App\Domains\Employee\Exports\EmployeeExporter;
use App\Domains\Employee\Exports\EmployeeProfileDocument;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Requests\SaveEmployeeSectionRequest;
use App\Domains\Employee\Requests\StoreEmployeeRequest;
use App\Domains\Employee\Requests\SubmitEmployeeRequest;
use App\Domains\Employee\Requests\UpdateEmployeeRequest;
use App\Domains\Employee\Resources\EmployeeResource;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Support\Exports\Concerns\StreamsTabularExports;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

class EmployeeController
{
    use StreamsTabularExports;

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
        private ExportService $exports,
        private DocumentExportService $documents,
        private FieldAccess $fieldAccess,
        private FormOptionService $formOptions,
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

    /**
     * Field catalog for the export dialog picker — same payload shape the
     * role-chart fields endpoint publishes, so one client picker serves both.
     */
    public function exportFields(): JsonResponse
    {
        $fields = collect($this->employeeService->exporter($this->baseQuery())->columns())
            ->map(fn ($column) => $column->toArray())
            ->values();

        return response()->json(['data' => $fields]);
    }

    /**
     * Fill-and-import template: the exporter's default columns, no data
     * rows, plus the `_meta` sheet when the format can host one.
     */
    public function exportTemplate(Request $request): SymfonyStreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        if (! in_array($format, ['xlsx', 'csv'], true)) {
            return response()->json(['message' => __('authorization.format_not_supported')], 422);
        }

        $file = $this->exports->template(
            $this->employeeService->exporter($this->baseQuery()),
            new ExportRequest(format: $format, options: EmployeeExporter::defaultOptions($format)),
        );

        return $this->streamExport($this->exports, $file);
    }

    /**
     * Streamed employee export. The query is scoped by the same rules as
     * index() and honors the same status filter, so the export never shows
     * more than the list the user is allowed to see.
     */
    public function export(Request $request): SymfonyStreamedResponse|JsonResponse
    {
        $format = $request->query('format', 'xlsx');

        if (! in_array($format, ['xlsx', 'csv', 'pdf', 'docx'], true)) {
            return response()->json(['message' => __('authorization.format_not_supported')], 422);
        }

        $file = $this->exports->run(
            $this->employeeService->exporter($this->scopedQuery($request)),
            new ExportRequest(
                fields: self::exportFieldsQuery($request),
                format: $format,
                options: in_array($format, ['pdf', 'docx'], true)
                    ? new ExportOptions
                    : EmployeeExporter::defaultOptions($format),
                presentation: self::exportPresentationQuery($request),
            ),
        );

        return $this->streamExport($this->exports, $file);
    }

    /**
     * The printable employee-profile document (one record as PDF/Word). Gated
     * by employee.view — the same permission as show() — and honors the same
     * deny-based field access, so the printout never contains what the API
     * strips. The document source resolves the actor's visible sections.
     */
    public function document(Request $request, Employee $employee): SymfonyStreamedResponse|JsonResponse
    {
        $this->authorization->authorize($request->user(), 'employee.view', $employee);

        $format = $request->query('format', 'pdf');

        if (! in_array($format, ['pdf', 'docx'], true)) {
            return response()->json(['message' => __('authorization.format_not_supported')], 422);
        }

        $employee->load(['user']);

        $source = new EmployeeProfileDocument(
            $this->employeeService,
            $this->fieldAccess,
            $this->formOptions,
            $request->user(),
        );

        $file = $this->documents->run($source, $employee, $format);

        return $this->streamExport($this->exports, $file);
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
     * The export base query — nothing attached yet. Exporters get their own
     * scope from the caller; see scopedQuery() and exporter().
     */
    private function baseQuery(): Builder
    {
        return Employee::query();
    }

    /**
     * Query with the exact visibility of index(): the same authorization
     * scope and the same employment-status filter, so an export never
     * exceeds what the user may list.
     */
    private function scopedQuery(Request $request): Builder
    {
        $query = $this->baseQuery();

        $this->authorization->scope($request->user(), 'employee.list', $query);

        if ($request->filled('status')) {
            $query->where('employment_status', $request->input('status'));
        }

        if ($request->filled('status_not')) {
            $query->where('employment_status', '!=', $request->input('status_not'));
        }

        return $query;
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
