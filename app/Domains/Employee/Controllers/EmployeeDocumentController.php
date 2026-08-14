<?php

namespace App\Domains\Employee\Controllers;

use App\Contracts\DocumentAuthorization;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private DocumentRepositoryInterface $documentRepository,
        private EmployeeService $employeeService,
        private DocumentCapabilities $documentCapabilities,
    ) {}

    public function index(Request $request, Employee $employee): JsonResponse
    {
        $documents = $this->documentService->getForEntity($employee);

        return response()->json([
            'data' => $documents
                ->flatMap(fn (Document $document) => $document->usages->map(
                    fn (DocumentUsage $usage) => $this->documentPayload($document, $usage),
                ))
                ->values(),
            'capabilities' => $this->documentCapabilities->forEntity($request->user(), $employee),
        ]);
    }

    public function requirements(): JsonResponse
    {
        return response()->json([
            'data' => $this->employeeService->getDocumentRequirements(),
        ]);
    }

    /**
     * Employee-scoped document library. Never a global list: only the current
     * employee's active, authorized documents are eligible (rule: employee
     * document library). The query is authorized server-side and may be further
     * narrowed to the categories compatible with a target placement.
     */
    public function library(Request $request, Employee $employee, DocumentAuthorization $authorization): JsonResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401);
        }

        if (! $authorization->authorize(
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

        $query = $authorization->scope($actor, DocumentAction::LibrarySelect, $query);

        if ($categoryIds !== null) {
            $query->whereHas(
                'document',
                fn ($q) => $q->whereIn('category_id', $categoryIds),
            );
        }

        $usages = $query->latest('document_usages.id')->get();

        return response()->json([
            'data' => $usages->map(
                fn (DocumentUsage $usage) => $this->documentPayload($usage->document, $usage),
            )->values(),
        ]);
    }

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        $file = $request->file('file');
        $category = DocumentCategory::where('id', $request->document_category_id)->firstOrFail();

        $requirements = $this->employeeService->getDocumentRequirements();
        $requirement = $requirements[$category->slug] ?? null;

        $validationErrors = $this->documentService->validateDocument($file, $requirement ?? []);

        if (! empty($validationErrors)) {
            return response()->json([
                'message' => $validationErrors[0],
                'errors' => $validationErrors,
            ], 422);
        }

        $sectionKey = $request->input('section_key');
        $fieldKey = $request->input('field_key');
        $notes = $request->input('notes');

        $usageCount = DocumentUsage::query()
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey())
            ->whereHas('document', fn ($query) => $query->where('category_id', $category->id))
            ->when($fieldKey !== null, fn ($query) => $query->where('field_key', $fieldKey))
            ->when($notes !== null, fn ($query) => $query->where('metadata->notes', $notes))
            ->count();

        if ($requirement && ($max = $requirement['max_files'] ?? null) !== null && $usageCount >= $max) {
            return response()->json([
                'message' => __('employee.documents.max_files_reached', ['count' => $max]),
            ], 422);
        }

        $totalMax = config('documents.employee.max_files', 20);
        $totalCount = DocumentUsage::query()
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey())
            ->count();
        if ($totalCount >= $totalMax) {
            return response()->json([
                'message' => __('employee.documents.total_max_files_reached', ['count' => $totalMax]),
            ], 422);
        }

        $metadata = array_filter([
            'notes' => $request->input('notes'),
            'meta' => $this->decodeJson($request->input('meta')),
            'form_data' => $this->decodeJson($request->input('form_data')),
        ], fn ($value) => $value !== null);

        $document = $this->documentService->upload(
            $employee,
            $file,
            $category,
            $sectionKey,
            $fieldKey,
            $metadata !== [] ? $metadata : null,
        );

        $usage = DocumentUsage::query()
            ->where('document_id', $document->id)
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->id)
            ->when($fieldKey !== null, fn ($query) => $query->where('field_key', $fieldKey))
            ->when($notes !== null, fn ($query) => $query->where('metadata->notes', $notes))
            ->latest('id')
            ->firstOrFail();

        return response()->json([
            'data' => $this->documentPayload($document, $usage),
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

        $category = $oldUsage->document->category;

        if ($category === null) {
            abort(422, __('employee.documents.invalid_category'));
        }

        $file = $request->file('file');
        $requirements = $this->employeeService->getDocumentRequirements();
        $requirement = $requirements[$category->slug] ?? null;

        $validationErrors = $this->documentService->validateDocument($file, $requirement ?? []);

        if (! empty($validationErrors)) {
            return response()->json([
                'message' => $validationErrors[0],
                'errors' => $validationErrors,
            ], 422);
        }

        $metadata = array_filter([
            'notes' => $request->input('notes'),
            'meta' => $this->decodeJson($request->input('meta')),
            'form_data' => $this->decodeJson($request->input('form_data')),
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

            $usage = DocumentUsage::query()
                ->where('document_id', $document->id)
                ->where('entity_type', Employee::class)
                ->where('entity_id', $employee->id)
                ->latest('id')
                ->firstOrFail();

            return [$document, $usage];
        });

        return response()->json([
            'data' => $this->documentPayload($document, $usage),
            'message' => __('employee.documents.replaced'),
        ], 201);
    }

    public function destroy(Employee $employee, int $usageId): JsonResponse
    {
        $deleted = $this->documentService->trashUsage($usageId, $employee);

        if (! $deleted) {
            abort(404);
        }

        return response()->json(['message' => __('employee.documents.trashed')]);
    }

    public function trashed(Request $request, Employee $employee): JsonResponse
    {
        $usages = DocumentUsage::query()
            ->withTrashed()
            ->whereNotNull('deleted_at')
            ->where('entity_type', Employee::class)
            ->where('entity_id', $employee->getKey())
            ->with('document')
            ->latest('deleted_at')
            ->get();

        return response()->json([
            'data' => $usages->map(
                fn (DocumentUsage $usage) => $this->documentPayload($usage->document, $usage),
            )->values(),
            'capabilities' => $this->documentCapabilities->forEntity($request->user(), $employee),
        ]);
    }

    public function restore(Employee $employee, int $usageId): JsonResponse
    {
        $restored = $this->documentService->restoreUsage($usageId, $employee);

        if (! $restored) {
            abort(404);
        }

        return response()->json(['message' => __('employee.documents.restored')]);
    }

    public function forceDestroy(Employee $employee, int $usageId): JsonResponse
    {
        $deleted = $this->documentService->forceDeleteUsage($usageId, $employee);

        if (! $deleted) {
            abort(404);
        }

        return response()->json(['message' => __('document.document_force_deleted')]);
    }

    public function serve(string $uuid, Request $request): StreamedResponse
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->whereHas('usages')
            ->firstOrFail();

        $disk = $document->disk;
        $path = $document->path;

        if ($request->boolean('thumbnail')) {
            $thumbPath = $this->documentRepository->getThumbnailPath($path);

            if (Storage::disk($disk)->exists($thumbPath)) {
                return Storage::disk($disk)->response($thumbPath);
            }
        }

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        if ($request->boolean('download')) {
            return Storage::disk($disk)->download($path, $this->documentService->downloadName($document));
        }

        return Storage::disk($disk)->response($path);
    }

    /**
     * Resolve the document category ids eligible for a target placement. Returns
     * null when no placement is given (every one of the employee's active
     * documents is eligible). When a placement is given, only categories whose
     * declared requirement matches the section/field are eligible.
     *
     * @return array<int, int>|null
     */
    private function compatibleCategoryIds(?string $sectionKey, ?string $fieldKey): ?array
    {
        if ($sectionKey === null && $fieldKey === null) {
            return null;
        }

        $slugs = collect($this->employeeService->getDocumentRequirements())
            ->filter(
                fn (array $requirement) => $sectionKey === null
                    || ($requirement['section_key'] ?? null) === $sectionKey,
            )
            ->filter(
                fn (array $requirement) => $fieldKey === null
                    || ($requirement['field_keys'] ?? null) === null
                    || in_array($fieldKey, $requirement['field_keys'], true),
            )
            ->keys()
            ->all();

        if ($slugs === []) {
            return [];
        }

        return DocumentCategory::whereIn('slug', $slugs)->pluck('id')->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(Document $document, DocumentUsage $usage): array
    {
        return [
            'id' => $document->id,
            'usage_id' => $usage->id,
            'document_id' => $document->id,
            'uuid' => $document->uuid,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'category' => $document->category ? [
                'id' => $document->category->id,
                'name' => $document->category->name,
                'slug' => $document->category->slug,
            ] : null,
            'structure_name' => $this->documentService->structureName($document, $usage),
            'section_key' => $usage->section_key,
            'field_key' => $usage->field_key,
            'notes' => $usage->metadata['notes'] ?? null,
            'metadata' => $usage->metadata ?? [],
            'deleted_at' => $usage->deleted_at?->toIso8601String(),
            'url' => URL::signedRoute(
                'employee.documents.serve',
                ['uuid' => $document->uuid],
            ),
            'download_url' => URL::signedRoute(
                'employee.documents.serve',
                ['uuid' => $document->uuid, 'download' => 1],
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
