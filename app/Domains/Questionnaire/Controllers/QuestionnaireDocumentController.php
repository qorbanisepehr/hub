<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentCapabilities;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Questionnaire\Requests\PublicStoreDocumentRequest;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionnaireDocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private QuestionnaireService $questionnaireService,
        private DocumentCapabilities $documentCapabilities,
    ) {}

    public function index(Request $request, string $uuid): JsonResponse
    {
        $questionnaire = $request->attributes->get('granted_resource');

        $documents = $this->documentService->getForEntity($questionnaire);

        return response()->json([
            'data' => $documents
                ->flatMap(fn (Document $document) => $document->usages->map(
                    fn (DocumentUsage $usage) => $this->documentService->documentPayload($document, $usage, 'questionnaire.documents.serve'),
                ))
                ->values(),
            'capabilities' => $this->documentCapabilities->forEntity($request->user(), $questionnaire),
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

        $usageCount = $this->documentService->usageCountForPlacement($questionnaire, $category->id, $fieldKey, $notes);

        if ($requirement && ($max = $requirement['max_files'] ?? null) !== null && $usageCount >= $max) {
            return response()->json([
                'message' => __('questionnaire.documents.max_files_reached', ['count' => $max]),
            ], 422);
        }

        $totalMax = $this->documentService->maxFilesFor($questionnaire);
        $totalCount = $this->documentService->totalUsageCount($questionnaire);
        if ($totalCount >= $totalMax) {
            return response()->json([
                'message' => __('questionnaire.documents.total_max_files_reached', ['count' => $totalMax]),
            ], 422);
        }

        $metadata = array_filter([
            'notes' => $request->input('notes'),
            'meta' => $this->documentService->decodeJson($request->input('meta')),
            'form_data' => $this->documentService->decodeJson($request->input('form_data')),
        ], fn ($value) => $value !== null);

        $document = $this->documentService->upload(
            $questionnaire,
            $file,
            $category,
            $sectionKey,
            $fieldKey,
            $metadata !== [] ? $metadata : null,
        );

        $usage = $this->documentService->newestUsageFor($questionnaire, $document->id, $fieldKey, $notes);

        return response()->json([
            'data' => $this->documentService->documentPayload($document, $usage, 'questionnaire.documents.serve'),
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

        return $this->documentService->serve($document, $request);
    }
}
