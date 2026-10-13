<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Contracts\BoardMeeting\RegistrySync;
use App\Domains\BoardMeeting\Events\BoardMeetingUpdated;
use App\Domains\BoardMeeting\Exports\BoardMeetingExporter;
use App\Domains\BoardMeeting\Exports\BoardMeetingPdfExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\BoardMeetingListRequest;
use App\Domains\BoardMeeting\Requests\UpdateBoardMeetingRequest;
use App\Domains\BoardMeeting\Resources\BoardMeetingResource;
use App\Domains\BoardMeeting\Services\BoardMeetingListQuery;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Support\Audit\AttributeDiff;
use App\Support\Exports\Concerns\StreamsTabularExports;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
     */
    public function exportPdf(BoardMeeting $meeting, DocumentExportService $documents): StreamedResponse
    {
        $file = $documents->run(new BoardMeetingPdfExport, $meeting, 'pdf');

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
