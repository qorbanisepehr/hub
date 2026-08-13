<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Requests\PublicStoreDocumentRequest;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionnaireDocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private DocumentRepositoryInterface $documentRepository,
        private QuestionnaireService $questionnaireService,
    ) {}

    public function index(Request $request, string $uuid): JsonResponse
    {
        $questionnaire = $request->attributes->get('granted_resource');

        $documents = $this->documentService->getForEntity($questionnaire);

        return response()->json([
            'data' => $documents
                ->flatMap(fn (Document $document) => $document->usages->map(
                    fn (DocumentUsage $usage) => $this->documentPayload($document, $usage),
                ))
                ->values(),
        ]);
    }

    public function requirements(): JsonResponse
    {
        return response()->json([
            'data' => $this->questionnaireService->getDocumentRequirements(),
        ]);
    }

    public function store(PublicStoreDocumentRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = $request->attributes->get('granted_resource');

        if (! $questionnaire->isDraft()) {
            return response()->json([
                'message' => __('questionnaire.questionnaire.not_draft'),
            ], 422);
        }

        $file = $request->file('file');
        $category = DocumentCategory::where('id', $request->document_category_id)->firstOrFail();

        $requirements = $this->questionnaireService->getDocumentRequirements();
        $requirement = $requirements[$category->slug] ?? null;

        // ── Validate document constraints ──
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
            ->where('entity_type', Questionnaire::class)
            ->where('entity_id', $questionnaire->getKey())
            ->whereHas('document', fn ($query) => $query->where('category_id', $category->id))
            ->when($fieldKey !== null, fn ($query) => $query->where('field_key', $fieldKey))
            ->when($notes !== null, fn ($query) => $query->where('metadata->notes', $notes))
            ->count();

        if ($requirement && ($max = $requirement['max_files'] ?? null) !== null && $usageCount >= $max) {
            return response()->json([
                'message' => __('questionnaire.documents.max_files_reached', ['count' => $max]),
            ], 422);
        }

        $totalMax = config('documents.questionnaire.max_files', 10);
        $totalCount = DocumentUsage::query()
            ->where('entity_type', Questionnaire::class)
            ->where('entity_id', $questionnaire->getKey())
            ->count();
        if ($totalCount >= $totalMax) {
            return response()->json([
                'message' => __('questionnaire.documents.total_max_files_reached', ['count' => $totalMax]),
            ], 422);
        }

        $metadata = array_filter([
            'notes' => $request->input('notes'),
            'meta' => $this->decodeJson($request->input('meta')),
            'form_data' => $this->decodeJson($request->input('form_data')),
        ], fn ($value) => $value !== null);

        $document = $this->documentService->upload(
            $questionnaire,
            $file,
            $category->slug,
            $sectionKey,
            $fieldKey,
            $metadata !== [] ? $metadata : null,
        );

        $usage = DocumentUsage::query()
            ->where('document_id', $document->id)
            ->where('entity_type', Questionnaire::class)
            ->where('entity_id', $questionnaire->id)
            ->when($fieldKey !== null, fn ($query) => $query->where('field_key', $fieldKey))
            ->when($notes !== null, fn ($query) => $query->where('metadata->notes', $notes))
            ->latest('id')
            ->firstOrFail();

        return response()->json([
            'data' => $this->documentPayload($document, $usage),
            'message' => __('document.document_uploaded'),
        ], 201);
    }

    public function destroy(Request $request, string $uuid, int $usageId): JsonResponse
    {
        $questionnaire = $request->attributes->get('granted_resource');

        $deleted = $this->documentService->deleteUsage($usageId, $questionnaire);

        if (! $deleted) {
            abort(404);
        }

        return response()->json(['message' => __('document.file_deleted')]);
    }

    public function serve(string $uuid, Request $request): StreamedResponse
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->whereHas('usages')
            ->firstOrFail();

        $disk = $document->disk;
        $path = $document->path;

        // Serve thumbnail if requested and it exists
        if ($request->boolean('thumbnail')) {
            $thumbPath = $this->documentRepository->getThumbnailPath($path);

            if (Storage::disk($disk)->exists($thumbPath)) {
                return Storage::disk($disk)->response($thumbPath);
            }
        }

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(Document $document, DocumentUsage $usage): array
    {
        return [
            'id' => $document->id,
            'usage_id' => $usage->id,
            'uuid' => $document->uuid,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'category_slug' => $document->category?->slug,
            'category_label' => $document->category?->name,
            'structure_name' => $this->documentService->structureName($document, $usage),
            'section_key' => $usage->section_key,
            'field_key' => $usage->field_key,
            'notes' => $usage->metadata['notes'] ?? null,
            'metadata' => $usage->metadata ?? [],
            'url' => URL::signedRoute(
                'questionnaire.documents.serve',
                ['uuid' => $document->uuid],
            ),
        ];
    }

    /**
     * Decode a JSON request field into an array (null-safe).
     *
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
