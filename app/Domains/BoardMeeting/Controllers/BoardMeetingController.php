<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Contracts\BoardMeeting\RegistrySync;
use App\Contracts\DocumentAuthorization;
use App\Domains\BoardMeeting\Events\BoardMeetingUpdated;
use App\Domains\BoardMeeting\Exports\BoardMeetingExporter;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Jobs\BuildBoardMeetingBundleJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\BoardMeetingBundleRequest;
use App\Domains\BoardMeeting\Requests\BoardMeetingListRequest;
use App\Domains\BoardMeeting\Requests\UpdateBoardMeetingRequest;
use App\Domains\BoardMeeting\Resources\BoardMeetingResource;
use App\Domains\BoardMeeting\Services\BoardMeetingBundle;
use App\Domains\BoardMeeting\Services\BoardMeetingListQuery;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Exceptions\DocumentZipLimitExceeded;
use App\Support\Audit\AttributeDiff;
use App\Support\Exports\BundleBuilder;
use App\Support\Exports\BundleFile;
use App\Support\Exports\BundleRequest;
use App\Support\Exports\BundleTooLarge;
use App\Support\Exports\Concerns\StreamsTabularExports;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\ExportService;
use App\Support\Exports\Queued\ExportFileStore;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
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
        BundleBuilder $bundles,
        DocumentAuthorization $documentAuthorization,
        BoardMeetingBundle $bundle,
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

        // Three renderings of the sheet ride along, and that is not redundancy.
        // The PDF is what gets printed and filed. The Word file is what somebody
        // opens when they mean to change something — an archive you cannot edit
        // is only half an archive. The Markdown is what greps, diffs, pastes into
        // an issue, or goes to an agent with no parser in between. One of the
        // three forces somebody to reconvert into the other two.
        $request_ = BundleRequest::sheetsWithAttachments(
            app(DocumentRendererRegistry::class),
            ['pdf', 'docx', 'md'],
        );

        $entries = $bundle->entries([$meeting], $request_);
        $entries = [...$entries, ...$bundle->manifestEntries(
            $bundle->manifest([$meeting], $entries, true),
        )];

        return $this->streamBundle(
            $bundles->build(
                $entries,
                filename: 'board-meeting-'.$meeting->getKey().'-'.now()->format('Y-m-d').'.zip',
                // Over the document cap by the sheets and the two manifest files
                // that are not documents: the cap counts documents, and none of
                // those is one.
                maxEntries: $limit + 5,
                maxBytes: (int) config('documents.zip.max_bytes'),
            ),
        );
    }

    /**
     * Many meetings as one archive.
     *
     * "All" means **all the list endpoint can show**, under the same filters and
     * the same route permission — not the whole table regardless of what the
     * user is looking at. That is why the selection is resolved through
     * `scopedQuery()` rather than a fresh unfiltered query: an export that
     * showed more than the list would be an export nobody asked for.
     *
     * When the result is too large the cap refuses with a 422 rather than
     * quietly raising a ceiling: "narrow your filter" is a better answer than a
     * * bundle that succeeds until the day it does not.
     *
     * Permissions are the two-part rule the single-meeting bundle uses. Sheets
     * are behind `board-meeting.export` (the route); attachments are behind
     * `board-meeting.documents.download` (the request, because only it knows
     * whether they were asked for) and then per usage, deny-all.
     */
    public function exportBundleBulk(
        BoardMeetingBundleRequest $bundleRequest,
        BoardMeetingListRequest $listRequest,
        DocumentRendererRegistry $renderers,
        BundleBuilder $bundles,
        DocumentAuthorization $documentAuthorization,
        BoardMeetingBundle $bundle,
        ExportFileStore $store,
    ): StreamedResponse|JsonResponse {
        // From the form request rather than an injected Request: it is already
        // the current request, and one fewer parameter is one fewer thing that
        // can resolve to nothing at dispatch time.
        $actor = $bundleRequest->user();

        if ($actor === null) {
            abort(401);
        }

        $meetings = $this->resolveBundleSelection($bundleRequest, $listRequest)
            ->load('documentUsages.document.category');

        if ($meetings->isEmpty()) {
            abort(422, __('board_meeting.print.bundle_empty'));
        }

        $wants = $bundleRequest->wantsAttachments();
        $formats = $bundleRequest->bundleFormats();

        if ($wants) {
            foreach ($meetings as $meeting) {
                foreach ($meeting->documentUsages as $usage) {
                    if (! $documentAuthorization->authorize(
                        $actor,
                        DocumentAction::Download,
                        DocumentAuthorizationContext::forUsage($usage),
                    )) {
                        abort(403, __('messages.permission_denied'));
                    }
                }
            }
        }

        // COUNT THE WORK BEFORE RENDERING ANY OF IT. One sheet per meeting per
        // format, the meeting's own files, and the two manifest pages — all
        // knowable from the selection alone. Deciding after the first hundred
        // PDF renders would mean building most of an archive before discovering
        // it has to be thrown away.
        $planned = $meetings->count() * count($formats)
            + ($wants ? $meetings->sum(fn (BoardMeeting $meeting): int => $meeting->documentUsages->count()) : 0)
            + 2;

        // Over the hard cap, refused here rather than queued: a job that will be
        // refused by the same cap an hour later has spent a worker to learn
        // nothing.
        if ($planned > (int) config('exports.bundle.max_entries')) {
            throw BundleTooLarge::forEntries($planned, (int) config('exports.bundle.max_entries'));
        }

        if ($planned > (int) config('exports.queued.sync_entry_limit')) {
            // The ids are frozen here, at request time, rather than the filters:
            // a filter means something different after a deploy than it did when
            // it was clicked, and a job that re-read it could build a different
            // archive than the one that was asked for.
            $token = $store->issueToken();

            BuildBoardMeetingBundleJob::dispatch(
                $token,
                $meetings->modelKeys(),
                $formats,
                $wants,
                $actor->getAuthIdentifier(),
            );

            return response()->json([
                'message' => __('board_meeting.print.bundle_queued'),
                'data' => [
                    'token' => $token,
                    'meeting_count' => $meetings->count(),
                ],
            ], 202);
        }

        $selection = $wants
            ? BundleRequest::sheetsWithAttachments($renderers, $formats)
            : BundleRequest::sheets($renderers, $formats);

        $built = $bundle->entries($meetings->all(), $selection);
        $built = [...$built, ...$bundle->manifestEntries(
            $bundle->manifest($meetings->all(), $built, $wants),
        )];

        return $this->streamBundle($bundles->build(
            $built,
            filename: 'board-meetings-'.now()->format('Y-m-d').'.zip',
            maxEntries: (int) config('exports.bundle.max_entries'),
            maxBytes: (int) config('exports.bundle.max_bytes'),
        ));
    }

    /**
     * The formats a bundle can be rendered in, with their labels.
     *
     * Read from the renderer registry rather than hardcoded in the dialog,
     * because a hardcoded list fails by omission rather than by error: the
     * dialog would simply not offer a format the server is perfectly happy to
     * produce, and nobody would find out until somebody noticed it missing.
     * Asking the registry is also what keeps this and the "unsupported format"
     * validation from being two separate truths about the same thing.
     */
    public function downloadBundle(string $token, ExportFileStore $store): StreamedResponse
    {
        $stored = $store->find($token);

        if ($stored === null) {
            abort(404, __('board_meeting.print.bundle_gone'));
        }

        return response()->streamDownload(function () use ($stored): void {
            readfile($stored['path']);
        }, $stored['filename'], [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Ask a queued bundle to stop.
     *
     * Cooperative, and answered the moment the request lands: the marker is
     * written here and the job reads it at the next meeting boundary. What it
     * cannot promise is that it stops *now* — the same limit the summary runs
     * have, and the same reason: a PHP process mid-render is not interruptible
     * from another request.
     */
    public function cancelBundle(string $token): JsonResponse
    {
        BuildBoardMeetingBundleJob::cancel($token);

        return response()->json([
            'message' => __('board_meeting.print.bundle_stop_requested'),
        ]);
    }

    public function bundleFormats(DocumentRendererRegistry $renderers): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (string $format): array => [
                    'key' => $format,
                    // Falls back to the key itself rather than to a blank row:
                    // a renderer added without a label should show as `wibble`
                    // in the dialog, which is visible, rather than as nothing,
                    // which is not.
                    'label' => self::formatLabel($format),
                ],
                $renderers->allFormats(),
            ),
        ]);
    }

    private static function formatLabel(string $format): string
    {
        $key = 'exports.formats.'.$format;
        $label = (string) __($key);

        return $label === $key ? $format : $label;
    }

    /**
     * The meetings a bulk bundle covers: an explicit selection when one was
     * given, otherwise everything the list endpoint would show under the
     * current filters.
     *
     * @return Collection<int, BoardMeeting>
     *
     * @throws ModelNotFoundException when a named meeting does not exist, so a
     *                                stale table cannot silently produce a bundle
     *                                of fewer meetings than the dialog promised
     */
    private function resolveBundleSelection(
        BoardMeetingBundleRequest $bundleRequest,
        BoardMeetingListRequest $listRequest,
    ): Collection {
        $ids = $bundleRequest->selectedIds();

        if ($ids === []) {
            return $this->scopedQuery($listRequest)->get();
        }

        $meetings = BoardMeeting::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        if ($meetings->count() !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(BoardMeeting::class, $ids);
        }

        return $meetings;
    }

    /**
     * Stream a finished archive, removing the temp file either way.
     */
    private function streamBundle(BundleFile $bundle): StreamedResponse
    {
        return response()->streamDownload(function () use ($bundle): void {
            try {
                readfile($bundle->path);
            } finally {
                $bundle->delete();
            }
        }, $bundle->filename, ['Content-Type' => $bundle->mimeType]);
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
     * The same sheet as JSON — the format a system on the other end reads.
     *
     * Worth an endpoint with no button behind it: an interchange surface is
     * legitimately API-first, and this is what makes a meeting legible to
     * whatever the hub is eventually wired to. The one registration in the
     * kernel gives the CV, the employee profile and the questionnaire their
     * JSON too, without a line of work in those domains.
     */
    public function exportJson(BoardMeeting $meeting, DocumentExportService $documents): StreamedResponse
    {
        return $this->streamMeetingSheet($meeting, $documents, 'json');
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
