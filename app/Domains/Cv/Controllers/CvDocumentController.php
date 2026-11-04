<?php

namespace App\Domains\Cv\Controllers;

use App\Domains\Cv\Requests\PublicStoreCvDocumentRequest;
use App\Domains\Cv\Services\CvService;
use App\Domains\Document\Controllers\Concerns\HandlesEntityDocuments;
use App\Domains\Document\Services\DocumentCapabilities;
use App\Domains\Document\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CvDocumentController extends Controller
{
    use HandlesEntityDocuments;

    public function __construct(
        private DocumentService $documentService,
        private CvService $cvService,
        private DocumentCapabilities $documentCapabilities,
    ) {}

    public function store(PublicStoreCvDocumentRequest $request, string $uuid): JsonResponse
    {
        return $this->storeDocument($request);
    }

    protected function documentDomain(): string
    {
        return 'cv';
    }

    /**
     * @return array<string, mixed>
     */
    protected function documentRequirements(): array
    {
        return $this->cvService->getDocumentRequirements();
    }

    protected function documentNotDraftKey(): string
    {
        return 'cv.not_draft';
    }
}
