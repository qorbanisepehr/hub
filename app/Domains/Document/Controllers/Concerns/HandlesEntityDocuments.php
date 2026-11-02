<?php

namespace App\Domains\Document\Controllers\Concerns;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentCapabilities;
use App\Domains\Document\Services\DocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared public document surface for the two candidate entities (Cv and
 * Questionnaire): list documents, upload one, delete a placement, serve the
 * file. These bodies used to be token-for-token twins in both domain
 * controllers — a per-domain wording or behavior change could land in one
 * copy and miss the other.
 *
 * Consuming controllers supply per-entity facts via the abstract hooks and
 * already promote `DocumentService` + `DocumentCapabilities`. The
 * domain-specific request class stays on the controller's `store()`
 * signature so Laravel resolves it for validation; the trait worker receives
 * the validated instance.
 *
 * @property DocumentService $documentService
 * @property DocumentCapabilities $documentCapabilities
 */
trait HandlesEntityDocuments
{
    /** Entity config/lang key, e.g. `cv` or `questionnaire`. */
    abstract protected function documentDomain(): string;

    /**
     * The entity's document requirements (category slug -> requirement), the
     * same array the upload worker consults for max-files limits.
     *
     * @return array<string, mixed>
     */
    abstract protected function documentRequirements(): array;

    /**
     * The not-draft failure translation key of the owning domain. The keys
     * sit differently (`cv.not_draft` vs
     * `questionnaire.questionnaire.not_draft`), so one derivation does not
     * fit both.
     */
    abstract protected function documentNotDraftKey(): string;

    public function index(Request $request, string $uuid): JsonResponse
    {
        $entity = $request->attributes->get('granted_resource');

        $documents = $this->documentService->getForEntity($entity);

        return response()->json([
            'data' => $documents
                ->flatMap(fn (Document $document) => $document->usages->map(
                    fn (DocumentUsage $usage) => $this->documentService->documentPayload($document, $usage, $this->documentServeRoute()),
                ))
                ->values(),
            'capabilities' => $this->documentCapabilities->forEntity($request->user(), $entity),
        ]);
    }

    public function storeDocument(FormRequest $request): JsonResponse
    {
        $entity = $request->attributes->get('granted_resource');

        if (! $entity->isDraft()) {
            return response()->json([
                'message' => __($this->documentNotDraftKey()),
            ], 422);
        }

        $file = $request->file('file');
        $category = DocumentCategory::where('id', $request->document_category_id)->firstOrFail();

        $requirements = $this->documentRequirements();
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

        $usageCount = $this->documentService->usageCountForPlacement($entity, $category->id, $fieldKey, $notes);

        if ($requirement && ($max = $requirement['max_files'] ?? null) !== null && $usageCount >= $max) {
            return response()->json([
                'message' => __($this->documentDomain().'.documents.max_files_reached', ['count' => $max]),
            ], 422);
        }

        $totalMax = $this->documentService->maxFilesFor($entity);
        $totalCount = $this->documentService->totalUsageCount($entity);
        if ($totalCount >= $totalMax) {
            return response()->json([
                'message' => __($this->documentDomain().'.documents.total_max_files_reached', ['count' => $totalMax]),
            ], 422);
        }

        $metadata = array_filter([
            'notes' => $request->input('notes'),
            'meta' => $this->documentService->decodeJson($request->input('meta')),
            'form_data' => $this->documentService->decodeJson($request->input('form_data')),
        ], fn ($value) => $value !== null);

        $document = $this->documentService->upload(
            $entity,
            $file,
            $category,
            $sectionKey,
            $fieldKey,
            $metadata !== [] ? $metadata : null,
        );

        $usage = $this->documentService->newestUsageFor($entity, $document->id, $fieldKey, $notes);

        return response()->json([
            'data' => $this->documentService->documentPayload($document, $usage, $this->documentServeRoute()),
            'message' => __('document.document_uploaded'),
        ], 201);
    }

    public function destroy(Request $request, string $uuid, int $usageId): JsonResponse
    {
        $entity = $request->attributes->get('granted_resource');

        $deleted = $this->documentService->deleteUsage($usageId, $entity);

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

    public function requirements(): JsonResponse
    {
        return response()->json([
            'data' => $this->documentRequirements(),
        ]);
    }

    /**
     * The route that serves a stored document's bytes to its owner — every
     * payload response links its usages against it, keyed by the domain.
     */
    private function documentServeRoute(): string
    {
        return $this->documentDomain().'.documents.serve';
    }
}
