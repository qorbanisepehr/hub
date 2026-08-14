<?php

namespace App\Domains\Cv\Controllers;

use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Requests\PublicStoreCvDocumentRequest;
use App\Domains\Cv\Services\CvService;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Document\Services\DocumentCapabilities;
use App\Domains\Document\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvDocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private DocumentRepositoryInterface $documentRepository,
        private CvService $cvService,
        private DocumentCapabilities $documentCapabilities,
    ) {}

    public function index(Request $request, string $uuid): JsonResponse
    {
        $cv = $request->attributes->get('granted_resource');

        $documents = $this->documentService->getForEntity($cv);

        return response()->json([
            'data' => $documents
                ->flatMap(fn (Document $document) => $document->usages->map(
                    fn (DocumentUsage $usage) => $this->documentPayload($document, $usage),
                ))
                ->values(),
            'capabilities' => $this->documentCapabilities->forEntity($request->user(), $cv),
        ]);
    }

    public function requirements(): JsonResponse
    {
        return response()->json([
            'data' => $this->cvService->getDocumentRequirements(),
        ]);
    }

    public function store(PublicStoreCvDocumentRequest $request, string $uuid): JsonResponse
    {
        $cv = $request->attributes->get('granted_resource');

        if (! $cv->isDraft()) {
            return response()->json([
                'message' => __('cv.not_draft'),
            ], 422);
        }

        $file = $request->file('file');
        $category = DocumentCategory::where('id', $request->document_category_id)->firstOrFail();

        $requirements = $this->cvService->getDocumentRequirements();
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
            ->where('entity_type', Cv::class)
            ->where('entity_id', $cv->getKey())
            ->whereHas('document', fn ($query) => $query->where('category_id', $category->id))
            ->when($fieldKey !== null, fn ($query) => $query->where('field_key', $fieldKey))
            ->when($notes !== null, fn ($query) => $query->where('metadata->notes', $notes))
            ->count();

        if ($requirement && ($max = $requirement['max_files'] ?? null) !== null && $usageCount >= $max) {
            return response()->json([
                'message' => __('cv.documents.max_files_reached', ['count' => $max]),
            ], 422);
        }

        $totalMax = config('documents.cv.max_files', 20);
        $totalCount = DocumentUsage::query()
            ->where('entity_type', Cv::class)
            ->where('entity_id', $cv->getKey())
            ->count();
        if ($totalCount >= $totalMax) {
            return response()->json([
                'message' => __('cv.documents.total_max_files_reached', ['count' => $totalMax]),
            ], 422);
        }

        $metadata = array_filter([
            'notes' => $request->input('notes'),
            'meta' => $this->decodeJson($request->input('meta')),
            'form_data' => $this->decodeJson($request->input('form_data')),
        ], fn ($value) => $value !== null);

        $document = $this->documentService->upload(
            $cv,
            $file,
            $category,
            $sectionKey,
            $fieldKey,
            $metadata !== [] ? $metadata : null,
        );

        $usage = DocumentUsage::query()
            ->where('document_id', $document->id)
            ->where('entity_type', Cv::class)
            ->where('entity_id', $cv->id)
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
        $cv = $request->attributes->get('granted_resource');

        $deleted = $this->documentService->deleteUsage($usageId, $cv);

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
            'url' => URL::signedRoute(
                'cv.documents.serve',
                ['uuid' => $document->uuid],
            ),
            'download_url' => URL::signedRoute(
                'cv.documents.serve',
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
