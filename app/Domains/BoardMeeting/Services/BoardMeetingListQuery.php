<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\BoardMeetingListRequest;
use App\Domains\Tagging\Services\TagService;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one scoping of the registry that the list, the export and the summary
 * bulk action all read through.
 *
 * It was a private method on the controller, which was correct while only the
 * list and the exporter used it — but the bulk action's candidate list has to
 * honour the same filters, because "generate the ones I am looking at" is the
 * difference between a hundred queued jobs and two. Duplicating the date
 * window, the Jalali year and the AND tag filter into a second place is
 * exactly the kind of copy that drifts: the export would keep agreeing with the
 * list while the bulk dialog quietly disagreed with both, and the drift would
 * only be visible as a wrong count.
 */
final readonly class BoardMeetingListQuery
{
    /** Global-search columns: the registry key and the human title. */
    private const SEARCHABLE = ['held_on_jalali', 'title'];

    public function __construct(
        private TagService $tags,
    ) {}

    /**
     * A builder for the meetings a list request is asking about, before any
     * page-level sort or pagination.
     *
     * `tags` is eager-loaded because every consumer of this scope — the list
     * rows, the exporter's tag column, the dialog's labels — needs it, and
     * leaving it to N+1 would be the one thing the shared scope is worse at
     * than the private method it replaced.
     */
    public function scoped(BoardMeetingListRequest $request): Builder
    {
        $query = BoardMeeting::query()->with('tags');

        if (($from = $request->listDateFrom()) !== null) {
            $query->whereDate('held_on', '>=', $from->toDateString());
        }

        if (($to = $request->listDateTo()) !== null) {
            $query->whereDate('held_on', '<=', $to->toDateString());
        }

        if (($year = $request->listJalaliYear()) !== null) {
            $query->where('held_on_jalali', 'like', $year.'-%');
        }

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        $this->tags->applyFilter($query, $request->listTagSlugs());

        return $query;
    }
}
