<?php

namespace App\Domains\Document\Controllers;

use App\Contracts\Documentable;
use App\Contracts\DocumentAuthorization;
use App\Domains\Audit\Services\AuditEventDispatcher;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Events\DocumentDeleted;
use App\Domains\Document\Events\DocumentDownloaded;
use App\Domains\Document\Events\DocumentRestored;
use App\Domains\Document\Events\DocumentUploaded;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Requests\StoreDocumentRequest;
use App\Domains\Document\Requests\StoreFromLibraryRequest;
use App\Domains\Document\Resources\DocumentResource;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Employee\Models\Employee;
use App\Domains\Questionnaire\Models\Questionnaire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const ROUTE_TYPE_MAP = [
        'employee' => Employee::class,
        'questionnaire' => Questionnaire::class,
    ];

    public function __construct(
        private readonly DocumentService $documentService,
        private readonly DocumentAuthorization $documentAuthorization,
        private readonly AuditEventDispatcher $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DocumentUsage::query()
            ->with('document')
            ->whereNull('document_usages.deleted_at');

        $this->applyEntityScope($query, $request);

        $query = $this->documentAuthorization->scope(
            $request->user(),
            DocumentAction::View,
            $query,
        );

        return DocumentResource::collection(
            $query->latest('document_usages.id')->paginate($request->input('per_page', 50)),
        );
    }

    public function store(StoreDocumentRequest $request): DocumentResource
    {
        $type = $request->input('documentable_type');
        $id = $request->input('documentable_id');
        $class = self::ROUTE_TYPE_MAP[$type] ?? null;

        if ($class === null) {
            abort(422, __('document.invalid_documentable_type'));
        }

        $owner = $class::query()->findOrFail($id);

        $category = DocumentCategory::findOrFail(
            $request->input('document_category_id'),
        );

        $this->authorizeRequest(
            $request,
            DocumentAction::Upload,
            DocumentAuthorizationContext::forOwner($owner),
        );

        $metadata = [];
        if ($notes = $request->input('notes')) {
            $metadata['notes'] = $notes;
        }

        $document = $this->documentService->upload(
            $owner,
            $request->file('file'),
            $category,
            $request->input('section_key'),
            $request->input('field_key'),
            $metadata !== [] ? $metadata : null,
        );

        $this->audit->record(new DocumentUploaded($document, $owner, $category->name));

        return new DocumentResource($document);
    }

    public function storeFromLibrary(StoreFromLibraryRequest $request): DocumentResource
    {
        $type = $request->input('documentable_type');
        $id = $request->input('documentable_id');
        $class = self::ROUTE_TYPE_MAP[$type] ?? null;

        if ($class === null) {
            abort(422, __('document.invalid_documentable_type'));
        }

        $owner = $class::query()->findOrFail($id);

        $source = Document::query()->findOrFail($request->input('source_document_id'));

        $actor = $request->user();

        if ($actor === null) {
            abort(401);
        }

        // Re-authorize at the point of operation: the actor must still be able
        // to reach the target owner, and the source must belong to that owner.
        // The library never reuses identities or moves documents across owners.
        if (! $this->documentAuthorization->authorize(
            $actor,
            DocumentAction::LibrarySelect,
            DocumentAuthorizationContext::forOwner($owner),
        )) {
            abort(403, __('messages.permission_denied'));
        }

        $sourceBelongsToTarget = DocumentUsage::query()
            ->where('entity_type', get_class($owner))
            ->where('entity_id', $owner->getKey())
            ->where('document_id', $source->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $sourceBelongsToTarget) {
            abort(403, __('messages.permission_denied'));
        }

        $metadata = [];
        if ($notes = $request->input('notes')) {
            $metadata['notes'] = $notes;
        }

        $document = $this->documentService->uploadFromLibrary(
            $owner,
            $source,
            $request->input('section_key'),
            $request->input('field_key'),
            $metadata !== [] ? $metadata : null,
        );

        return new DocumentResource($document);
    }

    public function show(Request $request, Document $document): DocumentResource
    {
        $this->authorizeDocument($request, $document, DocumentAction::View);

        $document->load('usages');

        return new DocumentResource($document);
    }

    /**
     * Move a single usage (identified by its id, exposed as `id` in the
     * resource) to the trash. The file is kept so it can be restored later.
     */
    public function destroy(Request $request, int $document): JsonResponse
    {
        $usage = DocumentUsage::find($document);
        $entity = $this->resolveUsageEntity($usage);

        if ($entity === null) {
            abort(404);
        }

        $this->authorizeRequest(
            $request,
            DocumentAction::Delete,
            DocumentAuthorizationContext::forUsage($usage),
        );

        $this->documentService->trashUsage($document, $entity);

        $this->audit->record(new DocumentDeleted($document, get_class($entity), $entity->getKey()));

        return response()->json(['message' => __('document.document_deleted')]);
    }

    public function trashed(Request $request): AnonymousResourceCollection
    {
        $query = DocumentUsage::query()
            ->onlyTrashed()
            ->with('document');

        $this->applyEntityScope($query, $request);

        $query = $this->documentAuthorization->scope(
            $request->user(),
            DocumentAction::View,
            $query,
            trashed: true,
        );

        return DocumentResource::collection(
            $query->latest('document_usages.id')->paginate($request->input('per_page', 50)),
        );
    }

    public function restore(Request $request, int $document): JsonResponse
    {
        $usage = DocumentUsage::onlyTrashed()->find($document);
        $entity = $this->resolveUsageEntity($usage);

        if ($entity === null) {
            abort(404);
        }

        $this->authorizeRequest(
            $request,
            DocumentAction::Restore,
            DocumentAuthorizationContext::forUsage($usage),
        );

        $this->documentService->restoreUsage($document, $entity);

        $this->audit->record(new DocumentRestored($document, get_class($entity), $entity->getKey()));

        return response()->json(['message' => __('document.document_restored')]);
    }

    public function forceDestroy(Request $request, int $document): JsonResponse
    {
        $usage = DocumentUsage::withTrashed()->find($document);
        $entity = $this->resolveUsageEntity($usage);

        if ($entity === null) {
            abort(404);
        }

        $this->authorizeRequest(
            $request,
            DocumentAction::ForceDelete,
            DocumentAuthorizationContext::forUsage($usage),
        );

        $this->documentService->forceDeleteUsage($document, $entity);

        return response()->json(['message' => __('document.document_force_deleted')]);
    }

    public function serve(Document $document, Request $request): StreamedResponse
    {
        $this->authorizeDocument($request, $document, DocumentAction::Download);

        $this->audit->record(new DocumentDownloaded($document));

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
        $this->authorizeDocument($request, $document, DocumentAction::Download);

        $this->audit->record(new DocumentDownloaded($document));

        $disk = $document->disk;
        $path = $document->path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->download(
            $path,
            $this->documentService->downloadName($document),
        );
    }

    private function applyEntityScope(Builder $query, Request $request): void
    {
        if ($type = $request->input('type')) {
            $class = self::ROUTE_TYPE_MAP[$type] ?? null;
            if ($class) {
                $query->where('entity_type', $class);
            }
        }

        if ($entityId = $request->input('id')) {
            $query->where('entity_id', $entityId);
        }
    }

    private function resolveUsageEntity(?DocumentUsage $usage): ?Documentable
    {
        if ($usage === null) {
            return null;
        }

        $entity = $usage->resolveEntity();

        return $entity instanceof Documentable ? $entity : null;
    }

    /**
     * Authorize an operation against a resource context, mapping a 403 onto a
     * `permission_denied` message. The route middleware already guarantees a
     * capability; this is the resource-level (policy) check.
     */
    private function authorizeRequest(
        Request $request,
        DocumentAction $action,
        DocumentAuthorizationContext $context,
    ): void {
        $actor = $request->user();

        if ($actor === null) {
            abort(401);
        }

        if (! $this->documentAuthorization->authorize($actor, $action, $context)) {
            abort(403, __('messages.permission_denied'));
        }
    }

    /**
     * Authorize a document-level operation against its first usage, falling
     * back to the bare document when no usage exists yet. Usage-level policies
     * (section_key, field_key) evaluate against the usage resource.
     */
    private function authorizeDocument(
        Request $request,
        Document $document,
        DocumentAction $action,
    ): void {
        if ($document->relationLoaded('usages')) {
            $usage = $document->usages->first();
        } else {
            $usage = $document->usages()->whereNull('document_usages.deleted_at')->first();
        }

        $this->authorizeRequest(
            $request,
            $action,
            $usage !== null
                ? DocumentAuthorizationContext::forUsage($usage)
                : DocumentAuthorizationContext::forDocument($document),
        );
    }

    private function getThumbnailPath(string $originalPath): string
    {
        $dir = pathinfo($originalPath, PATHINFO_DIRNAME);
        $name = pathinfo($originalPath, PATHINFO_FILENAME);
        $ext = pathinfo($originalPath, PATHINFO_EXTENSION);

        return "{$dir}/{$name}_thumb.{$ext}";
    }
}
