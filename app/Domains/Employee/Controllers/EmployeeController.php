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
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportFile;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\Exports\Value\PresentationOptions;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

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

        return $this->streamFile($file);
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
                fields: EmployeeController::fieldsFromQuery($request),
                format: $format,
                options: in_array($format, ['pdf', 'docx'], true)
                    ? new ExportOptions
                    : EmployeeExporter::defaultOptions($format),
                presentation: EmployeeController::presentationFromQuery($request),
            ),
        );

        return $this->streamFile($file);
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

        return $this->streamFile($file);
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
     * Stream an ExportFile as a download response.
     */
    private function streamFile(ExportFile $file): SymfonyStreamedResponse
    {
        return response()->streamDownload(function () use ($file): void {
            $stream = fopen('php://output', 'w');
            $file->copyTo($stream);
        }, $this->exports->dispositionFilename($file), [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Comma-separated `fields` query param → list<string> for ExportRequest.
     */
    private static function fieldsFromQuery(Request $request): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('fields', '')),
        )));
    }

    /**
     * Presentation query params → PresentationOptions. Whitelisted so a
     * stray query value degrades to the default (machine) form instead of
     * erroring.
     */
    private static function presentationFromQuery(Request $request): PresentationOptions
    {
        $headers = $request->query('headers');
        $calendar = $request->query('calendar');
        $digits = $request->query('digits');

        return new PresentationOptions(
            headers: in_array($headers, ['key', 'label'], true) ? $headers : 'key',
            calendar: in_array($calendar, ['gregorian', 'persian', 'both'], true) ? $calendar : 'gregorian',
            digits: $digits === 'persian' ? 'persian' : 'latin',
            detailSheets: $request->boolean('details'),
        );
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
