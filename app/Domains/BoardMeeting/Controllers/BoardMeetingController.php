<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\BoardMeetingListRequest;
use App\Domains\BoardMeeting\Resources\BoardMeetingResource;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Board meeting list/detail (Phase 3). The standard list contract speaks
 * through ListQuery (the same clamps/search helpers every list endpoint
 * uses); meetings are a local aggregate, so nothing here touches the
 * Reporting gateway pipeline. The resource collection supplies the
 * standard `data` + `meta` envelope over the paginator.
 */
class BoardMeetingController
{
    /** Sort whitelist + global-search columns. */
    private const SORTABLE = ['pdate', 'number'];

    private const SEARCHABLE = ['pdate', 'number'];

    public function index(BoardMeetingListRequest $request): AnonymousResourceCollection
    {
        $query = BoardMeeting::query()->with('tags');

        if (($from = $request->listDateFrom()) !== null) {
            $query->whereDate('date', '>=', $from->toDateString());
        }

        if (($to = $request->listDateTo()) !== null) {
            $query->whereDate('date', '<=', $to->toDateString());
        }

        ListQuery::search($query, $request->listFilter(), self::SEARCHABLE);

        $query->orderBy(
            ListQuery::sort($request, self::SORTABLE, 'pdate'),
            $request->listOrder(),
        );

        $meetings = $query->paginate(ListQuery::perPage($request, 20, 200));

        return BoardMeetingResource::collection($meetings);
    }

    public function show(Request $request, BoardMeeting $meeting): BoardMeetingResource
    {
        $meeting->load('tags');

        return new BoardMeetingResource($meeting);
    }
}
