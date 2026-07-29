<?php

namespace App\Domains\Document\Controllers;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Requests\StoreDocumentRequest;
use App\Domains\Document\Resources\DocumentResource;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Employee\Models\Employee;
use App\Domains\Recruitment\Models\Questionnaire;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends ApiController
{
    private const ROUTE_TYPE_MAP = [
        'employee' => Employee::class,
        'questionnaire' => Questionnaire::class,
    ];

    public function __construct(
        private readonly DocumentService $documentService,
    ) {
        $this->model = Document::class;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Document::query()
            ->with(['usages', 'usages.document'])
            ->whereHas('usages', function ($q) use ($request) {
                if ($type = $request->input('type')) {
                    $class = self::ROUTE_TYPE_MAP[$type] ?? null;
                    if ($class) {
                        $q->where('entity_type', $class);
                    }
                }

                if ($recordKey = $request->input('record_key')) {
                    $q->where('record_key', $recordKey);
                }
            });

        $documents = $query->latest()->paginate($request->input('per_page', 50));

        return DocumentResource::collection($documents);
    }

    public function store(StoreDocumentRequest $request): DocumentResource
    {
        $type = $request->input('documentable_type');
        $id = $request->input('documentable_id');
        $class = self::ROUTE_TYPE_MAP[$type] ?? null;

        if ($class === null) {
            abort(422, 'Invalid documentable type.');
        }

        $owner = $class::query()->findOrFail($id);

        $category = DocumentCategory::find(
            $request->input('document_category_id'),
        );

        $document = $this->documentService->upload(
            $owner,
            $request->file('file'),
            $category?->slug ?? 'general',
            $request->input('record_key'),
        );

        return new DocumentResource($document);
    }

    public function show(Document $document): DocumentResource
    {
        $document->load('usages');

        return new DocumentResource($document);
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->documentService->delete($document);

        return response()->json(['message' => 'Document deleted permanently.']);
    }

    public function trashed(Request $request): AnonymousResourceCollection
    {
        return DocumentResource::collection(
            Document::query()->whereRaw('1 = 0')->paginate(),
        );
    }

    public function restore(int $id): JsonResponse
    {
        abort(404, 'Restore is not supported. Documents are permanently deleted.');
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $this->documentService->delete($document);

        return response()->json(['message' => 'Document deleted permanently.']);
    }

    public function serve(Document $document, Request $request): StreamedResponse
    {
        $disk = $document->disk;
        $path = $document->path;

        if ($request->boolean('thumbnail')) {
            $thumbPath = $this->getThumbnailPath($path);

            if (Storage::disk($disk)->exists($thumbPath)) {
                return Storage::disk($disk)->response($thumbPath);
            }
        }

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path);
    }

    public function download(Document $document, Request $request): StreamedResponse
    {
        $disk = $document->disk;
        $path = $document->path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $extension = pathinfo($document->original_name, PATHINFO_EXTENSION);
        $filename = $document->original_name;

        return Storage::disk($disk)->download($path, $filename);
    }

    private function getThumbnailPath(string $originalPath): string
    {
        $dir = pathinfo($originalPath, PATHINFO_DIRNAME);
        $name = pathinfo($originalPath, PATHINFO_FILENAME);
        $ext = pathinfo($originalPath, PATHINFO_EXTENSION);

        return "{$dir}/{$name}_thumb.{$ext}";
    }
}
