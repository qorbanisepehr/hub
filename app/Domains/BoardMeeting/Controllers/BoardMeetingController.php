<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Contracts\BoardMeeting\RegistrySync;
use App\Contracts\DocumentAuthorization;
use App\Domains\BoardMeeting\Events\BoardMeetingUpdated;
use App\Domains\BoardMeeting\Exports\BoardMeetingExporter;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\BoardMeetingListRequest;
use App\Domains\BoardMeeting\Requests\UpdateBoardMeetingRequest;
use App\Domains\BoardMeeting\Resources\BoardMeetingResource;
use App\Domains\BoardMeeting\Services\BoardMeetingListQuery;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Exceptions\DocumentZipLimitExceeded;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentService;
use App\Support\Audit\AttributeDiff;
use App\Support\Exports\BundleBuilder;
use App\Support\Exports\BundleEntry;
use App\Support\Exports\Concerns\StreamsTabularExports;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Board meeting list/detail/update/export. The registry is a read-and-
 * curate surface: the bound source owns the registry columns and a person
 * owns the rest, so this controller reads the former and writes the
 * latter. When a person writes, the audit trail records the exact leaf
 * changes (ADR-008) — the trace that row passed to a human.
 */
class BoardMeetingController
{
    use StreamsTabularExports;

    /** Sort whitelist. */
    private const SORTABLE = ['held_on', 'sequence', 'imported_at'];

    public function __construct(
        private readonly ExportService $exports,
        private readonly BoardMeetingListQuery $listQuery,
        private readonly MeetingSummaryApplier $summaries,
    ) {}

    public function index(BoardMeetingListRequest $request): AnonymousResourceCollection
    {
        $query = $this->scopedQuery($request);

        $query->orderBy(
            ListQuery::sort($request, self::SORTABLE, 'held_on'),
            ListQuery::order($request),
        );

        return BoardMeetingResource::collection(
            $query->paginate(ListQuery::perPage($request, 20, 200)),
        );
    }

    /**
     * The selector catalog for the client export dialog.
     */
    public function exportFields(): JsonResponse
    {
        $fields = collect((new BoardMeetingExporter(BoardMeeting::query()))->columns())
            ->map(fn (ExportColumn $column) => $column->toArray())
            ->values();

        return response()->json(['data' => $fields]);
    }

    /**
     * Streamed registry export. The query is scoped by the same rules and
     * filters as index(), so an export never shows more than the list the
     * user is allowed to see. For PDF/Word the exporter's own title and
     * the kernel's sync row limit apply.
     */
    public function export(BoardMeetingListRequest $request): StreamedResponse|JsonResponse
    {
        $format = $request->query('format', 'xlsx');

        if (! in_array($format, ['xlsx', 'csv', 'pdf', 'docx'], true)) {
            return response()->json(['message' => __('authorization.format_not_supported')], 422);
        }

        $file = $this->exports->run(
            new BoardMeetingExporter($this->scopedQuery($request)),
            new ExportRequest(
                fields: self::exportFieldsQuery($request),
                format: $format,
                options: in_array($format, ['pdf', 'docx'], true)
                    ? new ExportOptions
                    : BoardMeetingExporter::defaultOptions($format),
                presentation: self::exportPresentationQuery($request),
            ),
        );

        return $this->streamExport($this->exports, $file);
    }

    /**
     * The printable form of ONE meeting — the detail page, on paper.
     *
     * A different pipeline from export() on purpose: that one streams a
     * registry-wide table through the tabular kernel, and a single meeting is
     * not a table of one row. This asks the document kernel instead, the same
     * path a CV or an employee profile takes, so RTL Persian, Jalali dates and
     * font embedding are already solved rather than re-solved here.
     *
     * Gated on `board-meeting.export` — the permission that already exists for
     * putting meetings into a file — rather than on `view`. Reading a meeting
     * and taking a copy of it away are different acts, the registry export
     * already draws that line, and a sheet is a copy.
     *
     * One action per format rather than a `?format=` parameter, because the
     * route is then self-describing and the client cannot ask for a format that
     * does not exist. Both bodies are one line into {@see streamMeetingSheet}
     * precisely so they cannot drift apart.
     */
    public function exportPdf(BoardMeeting $meeting, DocumentExportService $documents): StreamedResponse
    {
        return $this->streamMeetingSheet($meeting, $documents, 'pdf');
    }

    /**
     * Everything the meeting is, in one archive: the printable sheet plus the
     * scans filed under it.
     *
     * Two permissions, because the archive holds two kinds of thing. The sheet
     * needs `board-meeting.export` — the route's own gate. Each scan needs the
     * document download permission, which the authorization engine resolves from
     * the usage's owner, so this asks for it per usage rather than assuming one
     * permission covers both. Holding only the export permission gets you the
     * sheet and a 403 on the scans, which is exactly the line the PDF already
     * draws when it lists attachments by name and never their content.
     *
     * Deny-all for the same reason the document bundle is: a bundle that
     * quietly contained a file the user may not download would be a way around
     * the permission rather than a use of it. One denial refuses the request.
     *
     * Three renderings of the sheet ride along, and that is not redundancy. The
     * PDF is what gets printed and filed; the Word file is what somebody opens
     * when they mean to change something — an archive you cannot edit is only
     * half an archive; the Markdown is what greps, diffs, pastes into an issue,
     * or goes to an agent with no parser in between. One of the three forces
     * somebody to reconvert into the other two.
     *
     * Flat inside, and deliberately so. The document bundle groups by owner
     * because it can hold many entities; a single meeting has exactly one, so a
     * folder named after the meeting containing everything else would be a level
     * of nesting that teaches a reader nothing.
     */
    public function exportBundle(
        BoardMeeting $meeting,
        Request $request,
        DocumentExportService $exports,
        DocumentService $documentService,
        BundleBuilder $bundles,
        DocumentAuthorization $documentAuthorization,
    ): StreamedResponse {
        $actor = $request->user();

        if ($actor === null) {
            abort(401);
        }

        $meeting->loadMissing('documentUsages.document.category');

        $usages = $meeting->documentUsages;

        foreach ($usages as $usage) {
            if (! $documentAuthorization->authorize(
                $actor,
                DocumentAction::Download,
                DocumentAuthorizationContext::forUsage($usage),
            )) {
                abort(403, __('messages.permission_denied'));
            }
        }

        $limit = (int) config('documents.zip.max_files');

        if ($usages->count() > $limit) {
            throw DocumentZipLimitExceeded::forLimit($limit);
        }

        // The sheet is the one thing in here that is not a document, so it is
        // what this domain names itself — and it goes in three times, because the
        // three formats answer three different readers and an archive that only
        // holds one of them forces somebody to reconvert. The PDF is what gets
        // printed and filed. The Word file is what somebody opens when they mean
        // to change something — an archive you cannot edit is only half an
        // archive. The Markdown is what greps, diffs, pastes into an issue, and
        // can be handed to an agent with no parser in between. Clutter would be
        // a fourth rendering nobody asked for, not three that each have a
        // reader.
        $sheet = new BoardMeetingSheetExport;

        $entries = [
            BundleEntry::fromExport($exports->run($sheet, $meeting, 'pdf'), $this->sheetName($meeting, 'pdf')),
            BundleEntry::fromExport($exports->run($sheet, $meeting, 'docx'), $this->sheetName($meeting, 'docx')),
            BundleEntry::fromExport($exports->run($sheet, $meeting, 'md'), $this->sheetName($meeting, 'md')),
        ];

        foreach ($usages as $usage) {
            $document = $usage->document;

            $entries[] = BundleEntry::fromFile(
                path: Storage::disk($document->disk)->path($document->path),
                entryName: $this->bundleEntryName($document, $documentService, $usage),
            );
        }

        $bundle = $bundles->build(
            $entries,
            filename: 'board-meeting-'.$meeting->getKey().'-'.now()->format('Y-m-d').'.zip',
            // One over the document cap: the cap counts documents, and the
            // sheet is not one of them.
            // One over the document cap per sheet: the cap counts documents, and
            // none of the three sheets is one of them.
            maxEntries: $limit + 3,
            maxBytes: (int) config('documents.zip.max_bytes'),
        );

        return response()->streamDownload(function () use ($bundle): void {
            try {
                readfile($bundle->path);
            } finally {
                $bundle->delete();
            }
        }, $bundle->filename, ['Content-Type' => $bundle->mimeType]);
    }

    /**
     * The sheet's name inside the archive: the registry sequence when the
     * meeting has one, since that is how the meeting itself is called.
     */
    private function sheetName(BoardMeeting $meeting, string $extension): string
    {
        $stem = $meeting->sequence !== null && $meeting->sequence > 0
            ? 'meeting-'.$meeting->sequence
            : 'meeting-'.$meeting->getKey();

        return "{$stem}-summary.{$extension}";
    }

    /**
     * ASCII entry name from the same placement slug the document bundle uses,
     * so one scan reads identically whichever archive it arrived in.
     */
    private function bundleEntryName(Document $document, DocumentService $documentService, DocumentUsage $usage): string
    {
        $slug = $documentService->structureNames($document, $usage)['slug'];

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);

        return $extension !== '' ? "{$slug}.{$extension}" : $slug;
    }

    /**
     * The same sheet as a Word file.
     *
     * Worth having separately and not as a flag on the PDF action: a .docx is
     * editable, and "print this meeting" and "hand me something I can change"
     * are different requests that happen to share a body. The renderer differs
     * too — Word resolves its own fonts where mPDF embeds them — but none of
     * that is the domain's business, which is the point of going through the
     * document kernel.
     */
    public function exportDocx(BoardMeeting $meeting, DocumentExportService $documents): StreamedResponse
    {
        return $this->streamMeetingSheet($meeting, $documents, 'docx');
    }

    /**
     * The same sheet as Markdown.
     *
     * The one format that is not a document at all: no font to embed, no
     * pagination, no binary container. What it buys is that the meeting becomes
     * a file you can diff, grep, paste into an issue, or hand to an agent
     * without a parser — which in a hub that runs a summarizer is not a
     * novelty. It costs the layout the other two give you, and says so in the
     * renderer's own docblock rather than pretending a pipe table can be made
     * to read right-to-left.
     */
    public function exportMarkdown(BoardMeeting $meeting, DocumentExportService $documents): StreamedResponse
    {
        return $this->streamMeetingSheet($meeting, $documents, 'md');
    }

    /**
     * The one place a meeting is rendered to a file, whatever the file is.
     */
    private function streamMeetingSheet(BoardMeeting $meeting, DocumentExportService $documents, string $format): StreamedResponse
    {
        $file = $documents->run(new BoardMeetingSheetExport, $meeting, $format);

        return response()->streamDownload(function () use ($file): void {
            $stream = fopen('php://output', 'w');
            $file->copyTo($stream);
        }, $documents->dispositionFilename($file), [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The detail read.
     *
     * Before it answers, an in-flight stamp nobody is behind is cleared, so the
     * client's "still generating" state cannot outlive the run it stands for.
     * A write on a read path, deliberately: the alternative is to have the
     * client re-derive the queue's staleness rule, which is the one place such
     * a rule must not live. It is a no-op unless a row is actually stuck, so an
     * ordinary read stays a read.
     */
    public function show(BoardMeeting $meeting): BoardMeetingResource
    {
        $this->summaries->reapAbandonedRun($meeting);

        return new BoardMeetingResource($meeting->load('tags'));
    }

    /**
     * Save a person's edit and — only when something actually changed —
     * record the leaf-key diff as `board-meeting.updated`. The diff is the
     * audit hand-over: the row that just froze against the source.
     */
    public function update(UpdateBoardMeetingRequest $request, BoardMeeting $meeting): BoardMeetingResource
    {
        $old = $this->snapshot($meeting);

        $meeting->update($request->validated());

        $new = $this->snapshot($meeting);
        $changes = AttributeDiff::diff($old, $new);

        if ($changes['old'] !== [] || $changes['new'] !== []) {
            event(new BoardMeetingUpdated(
                $meeting,
                $changes['old'],
                $changes['new'],
            ));
        }

        return new BoardMeetingResource($meeting->load('tags'));
    }

    /**
     * Run the registry sync. Which source is behind the capability is
     * the integration's business, so this endpoint stays a one-liner
     * and works unchanged when a second source is bound.
     */
    public function sync(RegistrySync $sync): JsonResponse
    {
        return response()->json($sync->run());
    }

    /**
     * The query with the exact visibility of index(): the same date
     * window, Jalali year, AND-tag filter, and global search — so the
     * list and the export can never disagree about what is visible.
     */
    private function scopedQuery(BoardMeetingListRequest $request): Builder
    {
        return $this->listQuery->scoped($request);
    }

    /**
     * The human-owned columns as a comparable array. Attendees are read
     * through their cast so the diff compares concept values, not JSON.
     *
     * @return array{title: ?string, minutes: ?string, attendees: list<array{name: string, role: string, is_present: bool}>}
     */
    private function snapshot(BoardMeeting $meeting): array
    {
        return [
            'title' => $meeting->title,
            'minutes' => $meeting->minutes,
            'attendees' => array_map(
                fn (Attendee $attendee) => $attendee->toArray(),
                $meeting->attendees,
            ),
        ];
    }
}
