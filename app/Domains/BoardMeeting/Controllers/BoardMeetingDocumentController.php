<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Contracts\DocumentAuthorization;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\StoreBoardMeetingDocumentRequest;
use App\Domains\BoardMeeting\Requests\StoreBoardMeetingDocumentsBulkRequest;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentCapabilities;
use App\Domains\Document\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Board-meeting documents (K4 = hub Document system, CvDocumentController
 * pattern): scoped endpoints under a resolved meeting, gated by the
 * `board-meeting.documents.*` permission namespace. Meetings have no
 * sections/placements — documents attach flat to the meeting.
 */
class BoardMeetingDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly DocumentAuthorization $documentAuthorization,
        private readonly DocumentCapabilities $documentCapabilities,
    ) {}

    public function index(Request $request, BoardMeeting $meeting): JsonResponse
    {
        $documents = $this->documentService->getForEntity($meeting);

        return response()->json([
            'data' => $documents
                ->flatMap(fn (Document $document) => $document->usages->map(
                    fn (DocumentUsage $usage) => $this->documentService->documentPayload($document, $usage, 'board-meeting.documents.serve'),
                ))
                ->values(),
            'capabilities' => $this->documentCapabilities->forEntity($request->user(), $meeting),
        ]);
    }

    public function store(StoreBoardMeetingDocumentRequest $request, BoardMeeting $meeting): JsonResponse
    {
        // Category is fixed for meetings (idea basket, 2026-09-28): the
        // request resolves the approvals category; the UI needs no picker.
        $category = DocumentCategory::findOrFail($request->resolvedCategoryId());

        $this->authorizeRequest(
            $request,
            DocumentAction::Upload,
            DocumentAuthorizationContext::forOwner($meeting, category: $category),
        );

        // The registry number is the point of a meeting upload (vault idea
        // 567, order A): it is stored on the usage's metadata — where the
        // archive import and the export read it — not on the meeting.
        $metadata = ['registry_number' => $request->input('registry_number')];

        if ($title = $request->input('title')) {
            $metadata['title'] = $title;
        }

        if ($notes = $request->input('notes')) {
            $metadata['notes'] = $notes;
        }

        $document = $this->documentService->upload(
            $meeting,
            $request->file('file'),
            $category,
            metadata: $metadata,
        );

        $usage = $this->documentService->newestUsageFor($meeting, $document->id);

        return response()->json([
            'data' => $this->documentService->documentPayload($document, $usage, 'board-meeting.documents.serve'),
            'message' => __('document.document_uploaded'),
        ], 201);
    }

    /**
     * Import many مصوب in one pass. The response is a per-file report
     * (imported / skipped duplicated / failed), so a batch that mostly landed
     * is a 200 with the exceptions named — not a failed whole import. One
     * authorization for the whole batch: the capability is per meeting, not
     * per file.
     */
    public function bulkStore(StoreBoardMeetingDocumentsBulkRequest $request, BoardMeeting $meeting): JsonResponse
    {
        $category = DocumentCategory::findOrFail($request->resolvedCategoryId());

        $this->authorizeRequest(
            $request,
            DocumentAction::Upload,
            DocumentAuthorizationContext::forOwner($meeting, category: $category),
        );

        $entries = array_map(
            static fn (array $row): array => [
                'file' => $row['file'],
                'metadata' => array_filter([
                    'registry_number' => $row['registry_number'],
                    'title' => $row['title'],
                    'notes' => $row['notes'],
                ], static fn ($value) => $value !== null),
            ],
            $request->entries(),
        );

        $report = $this->documentService->bulkUpload(
            $meeting,
            $category,
            $entries,
            dedupeKey: 'registry_number',
            serveRouteName: 'board-meeting.documents.serve',
        );

        return response()->json([
            'data' => $report,
            'message' => __('document.bulk_uploaded', ['count' => count($report['imported'])]),
        ]);
    }

    public function destroy(Request $request, BoardMeeting $meeting, DocumentUsage $usage): JsonResponse
    {
        $this->authorizeRequest(
            $request,
            DocumentAction::Delete,
            DocumentAuthorizationContext::forUsage($usage),
        );

        $deleted = $this->documentService->deleteUsage($usage->id, $meeting);

        if (! $deleted) {
            abort(404);
        }

        return response()->json(['message' => __('document.file_deleted')]);
    }

    public function serve(Request $request, string $uuid): StreamedResponse
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->whereHas('usages', fn ($query) => $query->where('entity_type', BoardMeeting::class))
            ->firstOrFail();

        return $this->documentService->serve($document, $request);
    }

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
}
