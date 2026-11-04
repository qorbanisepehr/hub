<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Domains\Document\Controllers\Concerns\HandlesEntityDocuments;
use App\Domains\Document\Services\DocumentCapabilities;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Questionnaire\Requests\PublicStoreDocumentRequest;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class QuestionnaireDocumentController extends Controller
{
    use HandlesEntityDocuments;

    public function __construct(
        private DocumentService $documentService,
        private QuestionnaireService $questionnaireService,
        private DocumentCapabilities $documentCapabilities,
    ) {}

    public function store(PublicStoreDocumentRequest $request, string $uuid): JsonResponse
    {
        return $this->storeDocument($request);
    }

    protected function documentDomain(): string
    {
        return 'questionnaire';
    }

    /**
     * @return array<string, mixed>
     */
    protected function documentRequirements(): array
    {
        return $this->questionnaireService->getDocumentRequirements();
    }

    protected function documentNotDraftKey(): string
    {
        return 'questionnaire.questionnaire.not_draft';
    }
}
