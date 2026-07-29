<?php

namespace App\Domains\Recruitment\Controllers;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Requests\PublicStoreDocumentRequest;
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
    ) {}

    public function index(string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->firstOrFail();

        $documents = $this->documentService->getForEntity($questionnaire);

        return response()->json([
            'data' => $documents->map(fn (Document $doc) => [
                'id' => $doc->id,
                'uuid' => $doc->uuid,
                'original_name' => $doc->original_name,
                'mime_type' => $doc->mime_type,
                'size' => $doc->size,
                'category_slug' => $doc->usages->first()?->category_slug,
                'record_key' => $doc->usages->first()?->record_key,
                'slot' => $doc->usages->first()?->slot,
                'url' => URL::temporarySignedRoute(
                    'questionnaire.documents.serve',
                    now()->addHours(2),
                    ['documentId' => $doc->id],
                ),
            ]),
        ]);
    }

    public function store(PublicStoreDocumentRequest $request, string $uuid): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        $file = $request->file('file');
        $category = DocumentCategory::where('id', $request->document_category_id)->firstOrFail();

        $document = $this->documentService->upload(
            $questionnaire,
            $file,
            $category->slug,
            $request->input('record_key'),
            $request->input('slot'),
        );

        return response()->json([
            'data' => [
                'id' => $document->id,
                'uuid' => $document->uuid,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'category_slug' => $document->usages->first()?->category_slug,
                'record_key' => $document->usages->first()?->record_key,
                'url' => URL::temporarySignedRoute(
                    'questionnaire.documents.serve',
                    now()->addHours(2),
                    ['documentId' => $document->id],
                ),
            ],
            'message' => 'Document uploaded successfully.',
        ], 201);
    }

    public function destroy(string $uuid, int $documentId): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->firstOrFail();

        $document = Document::where('id', $documentId)
            ->whereHas('usages', function ($q) use ($questionnaire) {
                $q->where('entity_type', Questionnaire::class)
                    ->where('entity_id', $questionnaire->id);
            })
            ->firstOrFail();

        $this->documentService->delete($document);

        return response()->json(['message' => 'فایل حذف شد.']);
    }

    public function serve(int $documentId, Request $request): StreamedResponse
    {
        $document = Document::findOrFail($documentId);

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
}
