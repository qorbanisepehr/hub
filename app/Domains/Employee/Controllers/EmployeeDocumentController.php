<?php

namespace App\Domains\Employee\Controllers;

use App\Contracts\DocumentAuthorization;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Events\DocumentDeleted;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentCapabilities;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Requests\ReplaceEmployeeDocumentRequest;
use App\Domains\Employee\Requests\StoreEmployeeDocumentRequest;
use App\Domains\Employee\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private EmployeeService $employeeService,
        private DocumentCapabilities $documentCapabilities,
        private DocumentAuthorization $documentAuthorization,
    ) {}

    public function index(Request $request, Employee $employee): JsonResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401);
        }

        $this->authorizeEmployee($request, $employee, DocumentAction::View);

        $query = DocumentUsage::query()
            ->with('document')
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey());

        $query = $this->documentAuthorization->scope($actor, DocumentAction::View, $query);

        $usages = $query->latest('document_usages.id')->get();

        return response()->json([
            'data' => $usages->map(
                fn (DocumentUsage $usage) => $this->documentService->documentPayload($usage->document, $usage, 'employee.documents.serve'),
            )->values(),
            'capabilities' => $this->documentCapabilities->forEntity($actor, $employee),
        ]);
    }

    public function requirements(): JsonResponse
    {
        return response()->json([
            'data' => $this->employeeService->getDocumentRequirements(),
            // Additive sibling: dynamic placement groups (pattern-scoped
            // requirement sets, e.g. one per dependent row) for the client's
            // placement-aware caps and completeness warnings.
            'dynamic_requirements' => $this->employeeService->getDynamicDocumentRequirements(),
        ]);
    }

    /**
     * Employee-scoped document library. Never a global list: only the current
     * employee's active, authorized documents are eligible (rule: employee
     * document library). The query is authorized server-side and may be further
     * narrowed to the categories compatible with a target placement.
     */
    public function library(Request $request, Employee $employee): JsonResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401);
        }

        if (! $this->documentAuthorization->authorize(
            $actor,
            DocumentAction::LibrarySelect,
            DocumentAuthorizationContext::forOwner($employee),
        )) {
            abort(403, __('messages.permission_denied'));
        }

        $categoryIds = $this->compatibleCategoryIds(
            $request->input('section_key'),
            $request->input('field_key'),
        );

        $query = DocumentUsage::query()
            ->with('document')
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey());

        $query = $this->documentAuthorization->scope($actor, DocumentAction::LibrarySelect, $query);

        if ($categoryIds !== null) {
            $query->whereHas(
                'document',
                fn ($q) => $q->whereIn('category_id', $categoryIds),
            );
        }

        $usages = $query->latest('document_usages.id')->get();

        return response()->json([
            'data' => $usages->map(
                fn (DocumentUsage $usage) => $this->documentService->documentPayload($usage->document, $usage, 'employee.documents.serve'),
            )->values(),
        ]);
    }

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        $this->authorizeEmployee($request, $employee, DocumentAction::Upload);

        $file = $request->file('file');
        $category = DocumentCategory::where('id', $request->document_category_id)->firstOrFail();

        $sectionKey = $request->input('section_key');
        $fieldKey = $request->input('field_key');
        $notes = $request->input('notes');

        $requirement = $this->employeeService
            ->resolveDocumentRequirement($category->slug, $sectionKey, $fieldKey);

        $validationErrors = $this->documentService->validateDocument($file, $requirement ?? []);

        if (! empty($validationErrors)) {
            return response()->json([
                'message' => $validationErrors[0],
                'errors' => $validationErrors,
            ], 422);
        }

        $usageCount = $this->documentService->usageCountForPlacement($employee, $category->id, $fieldKey, $notes);

        if ($requirement && ($max = $requirement['max_files'] ?? null) !== null && $usageCount >= $max) {
            return response()->json([
                'message' => __('employee.documents.max_files_reached', ['count' => $max]),
            ], 422);
        }

        $totalMax = $this->documentService->maxFilesFor($employee);
        $totalCount = $this->documentService->totalUsageCount($employee);
        if ($totalCount >= $totalMax) {
            return response()->json([
                'message' => __('employee.documents.total_max_files_reached', ['count' => $totalMax]),
            ], 422);
        }

        $metadata = array_filter([
            'notes' => $request->input('notes'),
            'meta' => $this->documentService->decodeJson($request->input('meta')),
            'form_data' => $this->documentService->decodeJson($request->input('form_data')),
        ], fn ($value) => $value !== null);

        $document = $this->documentService->upload(
            $employee,
            $file,
            $category,
            $sectionKey,
            $fieldKey,
            $metadata !== [] ? $metadata : null,
        );

        $usage = $this->documentService->newestUsageFor($employee, $document->id, $fieldKey, $notes);

        return response()->json([
            'data' => $this->documentService->documentPayload($document, $usage, 'employee.documents.serve'),
            'message' => __('document.document_uploaded'),
        ], 201);
    }

    public function replace(ReplaceEmployeeDocumentRequest $request, Employee $employee, int $usageId): JsonResponse
    {
        $oldUsage = DocumentUsage::query()
            ->whereKey($usageId)
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey())
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->authorizeEmployee($request, $employee, DocumentAction::Replace, $oldUsage);

        $category = $oldUsage->document->category;

        if ($category === null) {
            abort(422, __('employee.documents.invalid_category'));
        }

        $file = $request->file('file');
        $requirement = $this->employeeService
            ->resolveDocumentRequirement($category->slug, $oldUsage->section_key, $oldUsage->field_key);

        $validationErrors = $this->documentService->validateDocument($file, $requirement ?? []);

        if (! empty($validationErrors)) {
            return response()->json([
                'message' => $validationErrors[0],
                'errors' => $validationErrors,
            ], 422);
        }

        $metadata = array_filter([
            'notes' => $request->input('notes'),
            'meta' => $this->documentService->decodeJson($request->input('meta')),
            'form_data' => $this->documentService->decodeJson($request->input('form_data')),
        ], fn ($value) => $value !== null);

        [$document, $usage] = DB::transaction(function () use ($employee, $file, $category, $oldUsage, $metadata) {
            $document = $this->documentService->upload(
                $employee,
                $file,
                $category,
                $oldUsage->section_key,
                $oldUsage->field_key,
                $metadata !== [] ? $metadata : null,
            );

            $oldUsage->delete();

            $usage = $this->documentService->newestUsageFor($employee, $document->id);

            return [$document, $usage];
        });

        return response()->json([
            'data' => $this->documentService->documentPayload($document, $usage, 'employee.documents.serve'),
            'message' => __('employee.documents.replaced'),
        ], 201);
    }

    public function destroy(Request $request, Employee $employee, int $usageId): JsonResponse
    {
        $usage = DocumentUsage::query()
            ->whereKey($usageId)
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey())
            ->whereNull('deleted_at')
            ->first();

        if ($usage === null) {
            abort(404);
        }

        $this->authorizeEmployee($request, $employee, DocumentAction::Delete, $usage);

        $deleted = $this->documentService->trashUsage($usageId, $employee);

        if (! $deleted) {
            abort(404);
        }

        return response()->json(['message' => __('employee.documents.trashed')]);
    }

    public function trashed(Request $request, Employee $employee): JsonResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401);
        }

        $this->authorizeEmployee($request, $employee, DocumentAction::View);

        $query = DocumentUsage::query()
            ->withTrashed()
            ->whereNotNull('document_usages.deleted_at')
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey())
            ->with('document');

        $query = $this->documentAuthorization->scope($actor, DocumentAction::View, $query, trashed: true);

        $usages = $query->latest('document_usages.deleted_at')->get();

        return response()->json([
            'data' => $usages->map(
                fn (DocumentUsage $usage) => $this->documentService->documentPayload($usage->document, $usage, 'employee.documents.serve'),
            )->values(),
            'capabilities' => $this->documentCapabilities->forEntity($actor, $employee),
        ]);
    }

    public function restore(Request $request, Employee $employee, int $usageId): JsonResponse
    {
        $usage = DocumentUsage::query()
            ->withTrashed()
            ->whereKey($usageId)
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey())
            ->whereNotNull('deleted_at')
            ->first();

        if ($usage === null) {
            abort(404);
        }

        $this->authorizeEmployee($request, $employee, DocumentAction::Restore, $usage);

        $restored = $this->documentService->restoreUsage($usageId, $employee);

        if (! $restored) {
            abort(404);
        }

        return response()->json(['message' => __('employee.documents.restored')]);
    }

    public function forceDestroy(Request $request, Employee $employee, int $usageId): JsonResponse
    {
        $usage = DocumentUsage::query()
            ->withTrashed()
            ->whereKey($usageId)
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey())
            ->whereNotNull('deleted_at')
            ->first();

        if ($usage === null) {
            abort(404);
        }

        $this->authorizeEmployee($request, $employee, DocumentAction::ForceDelete, $usage);

        $deleted = $this->documentService->forceDeleteUsage($usageId, $employee);

        if (! $deleted) {
            abort(404);
        }

        event(new DocumentDeleted($usageId, Employee::class, $employee->getKey()));

        return response()->json(['message' => __('document.document_force_deleted')]);
    }

    public function serve(string $uuid, Request $request): StreamedResponse
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->whereHas('usages')
            ->firstOrFail();

        return $this->documentService->serve($document, $request);
    }

    /**
     * Resource-level authorization for an employee document operation. The route
     * middleware only checks the capability; this enforces the rule against the
     * actual owner (and optionally the target usage for policy evaluation).
     */
    private function authorizeEmployee(Request $request, Employee $employee, DocumentAction $action, ?DocumentUsage $usage = null): void
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401);
        }

        $context = $usage !== null
            ? DocumentAuthorizationContext::forUsage($usage)
            : DocumentAuthorizationContext::forOwner($employee);

        if (! $this->documentAuthorization->authorize($actor, $action, $context)) {
            abort(403, __('messages.permission_denied'));
        }
    }

    /**
     * Resolve the document category ids eligible for a target placement. Returns
     * null when no placement is given (every one of the employee's active
     * documents is eligible). Placement semantics live on the section registry.
     *
     * @return array<int, int>|null
     */
    private function compatibleCategoryIds(?string $sectionKey, ?string $fieldKey): ?array
    {
        $slugs = $this->employeeService->documentCategorySlugsForPlacement($sectionKey, $fieldKey);

        if ($slugs === null) {
            return null;
        }

        if ($slugs === []) {
            return [];
        }

        return DocumentCategory::whereIn('slug', $slugs)->pluck('id')->all();
    }
}
