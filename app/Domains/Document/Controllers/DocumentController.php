<?php

namespace App\Domains\Document\Controllers;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Requests\StoreDocumentRequest;
use App\Domains\Document\Resources\DocumentResource;
use App\Domains\Document\Services\DocumentService;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends ApiController
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {
        $this->model = Document::class;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Document::with(['category', 'currentRevision', 'uploader']);

        if ($type = $request->input('type')) {
            $class = Document::routeTypeMap()[$type] ?? null;
            if ($class) {
                $query->where('documentable_type', $class);
            }
        }

        if ($recordKey = $request->input('record_key')) {
            $query->where('record_key', $recordKey);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $this->scopeQuery($query, $request, 'uploaded_by');

        $documents = $query->latest()->paginate($request->input('per_page', 50));

        return DocumentResource::collection($documents);
    }

    public function store(StoreDocumentRequest $request): DocumentResource
    {
        $type = $request->input('documentable_type');
        $id = $request->input('documentable_id');
        $class = Document::routeTypeMap()[$type] ?? null;

        if ($class === null) {
            abort(422, __('document.invalid_documentable_type'));
        }

        $owner = $class::query()->findOrFail($id);

        $document = $this->documentService->upload($owner, $request->file('file'), [
            'document_category_id' => $request->document_category_id,
            'notes' => $request->notes,
            'meta' => $request->filled('meta') ? json_decode($request->meta, true) : null,
            'record_key' => $request->record_key,
            'form_data' => $request->filled('form_data') ? json_decode($request->form_data, true) : null,
        ]);

        $document->load(['category', 'currentRevision', 'uploader']);

        return new DocumentResource($document);
    }

    public function show(Document $document): DocumentResource
    {
        $document->load(['category', 'currentRevision', 'uploader', 'revisions']);

        return new DocumentResource($document);
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->documentService->delete($document);

        return response()->json(['message' => __('document.document_deleted')]);
    }

    public function trashed(Request $request): AnonymousResourceCollection
    {
        $query = Document::onlyTrashed()
            ->with(['category', 'currentRevision', 'uploader']);

        if ($type = $request->input('type')) {
            $class = Document::routeTypeMap()[$type] ?? null;
            if ($class) {
                $query->where('documentable_type', $class);
            }
        }

        $this->scopeQuery($query, $request, 'uploaded_by');

        $documents = $query->latest('deleted_at')->paginate($request->input('per_page', 50));

        return DocumentResource::collection($documents);
    }

    public function restore(int $id): DocumentResource
    {
        $document = Document::onlyTrashed()->findOrFail($id);

        $document->restore();

        $document->load(['category', 'currentRevision', 'uploader']);

        return new DocumentResource($document);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $document = Document::onlyTrashed()->findOrFail($id);

        $this->documentService->forceDelete($document);

        return response()->json(['message' => __('document.document_force_deleted')]);
    }

    public function download(Document $document, Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('download', $document);

        $revision = $document->currentRevision;

        if ($revision === null) {
            abort(404);
        }

        $disk = config('documents.storage_disk');

        if (! Storage::disk($disk)->exists($revision->stored_path)) {
            abort(404);
        }

        $extension = pathinfo($revision->original_name, PATHINFO_EXTENSION);
        $filename = $document->category?->slug
            .($extension ? '.'.$extension : '');

        return Storage::disk($disk)->download($revision->stored_path, $filename);
    }

    public function serve(Document $document, Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('download', $document);

        $revision = $document->currentRevision;

        if ($revision === null) {
            abort(404);
        }

        $disk = config('documents.storage_disk');

        $useThumbnail = $request->boolean('thumbnail') && $revision->thumbnail_path;
        $path = $useThumbnail ? $revision->thumbnail_path : $revision->stored_path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path);
    }

    public function confirm(Document $document, Request $request): DocumentResource
    {
        $document = $this->documentService->confirm(
            $document,
            $request->input('notes'),
        );

        $document->load(['category', 'currentRevision', 'uploader']);

        return new DocumentResource($document);
    }

    public function reject(Document $document, Request $request): DocumentResource
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $document = $this->documentService->reject(
            $document,
            $request->reason,
        );

        $document->load(['category', 'currentRevision', 'uploader']);

        return new DocumentResource($document);
    }
}
