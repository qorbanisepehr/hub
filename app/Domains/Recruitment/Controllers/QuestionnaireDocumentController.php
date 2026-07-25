<?php

namespace App\Domains\Recruitment\Controllers;

use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Resources\QuestionnaireDocumentResource;
use App\Domains\Recruitment\Models\Questionnaire;
use App\Domains\Recruitment\Requests\StoreQuestionnaireDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionnaireDocumentController extends Controller
{
    public function index(string $uuid, Request $request): AnonymousResourceCollection
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->firstOrFail();

        $documents = $questionnaire->documents()
            ->with(['category'])
            ->latest()
            ->get();

        return QuestionnaireDocumentResource::collection($documents);
    }

    public function store(StoreQuestionnaireDocumentRequest $request, string $uuid): QuestionnaireDocumentResource
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();

        $file = $request->file('file');
        $categorySlug = DocumentCategory::where('id', $request->document_category_id)->value('slug');

        $path = $file->store(
            'questionnaires/'.$questionnaire->uuid.'/documents/'.$categorySlug,
            config('documents.storage_disk'),
        );

        $document = $questionnaire->documents()->create([
            'document_category_id' => $request->document_category_id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $request->notes,
            'meta' => $request->meta ? json_decode($request->meta, true) : null,
        ]);

        GenerateDocumentThumbnail::dispatch($document);

        $document->load(['category']);

        return new QuestionnaireDocumentResource($document);
    }

    public function destroy(string $uuid, int $documentId): JsonResponse
    {
        $questionnaire = Questionnaire::where('uuid', $uuid)->firstOrFail();

        $document = $questionnaire->documents()->where('id', $documentId)->firstOrFail();

        $disk = config('documents.storage_disk');
        Storage::disk($disk)->delete(array_filter([$document->stored_path, $document->thumbnail_path]));

        $document->forceDelete();

        return response()->json(['message' => 'فایل حذف شد.']);
    }

    public function serve(int $documentId, Request $request): StreamedResponse
    {
        $document = Document::where('id', $documentId)->firstOrFail();

        $disk = config('documents.storage_disk');

        $useThumbnail = $request->boolean('thumbnail') && $document->thumbnail_path;
        $path = $useThumbnail ? $document->thumbnail_path : $document->stored_path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path);
    }
}
