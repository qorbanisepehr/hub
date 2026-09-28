<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\BoardMeetingListRequest;
use App\Domains\BoardMeeting\Requests\UpdateBoardMeetingRequest;
use App\Domains\BoardMeeting\Resources\BoardMeetingResource;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Domains\Tagging\Services\TagService;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Board meeting list/detail/update (fresh shape, doc 060). TCK-parity
 * editability: the importer owns creation, the UI owns updating
 * (title/status/minutes/number) — TCK had an update endpoint too. The
 * detail response embeds the letters context (read-only, from the
 * generic Ingest layer) exactly like TCK's show.
 */
class BoardMeetingController
{
    /** Sort whitelist + global-search columns. */
    private const SORTABLE = ['date', 'number'];

    private const SEARCHABLE = ['number', 'title'];

    public function index(BoardMeetingListRequest $request): AnonymousResourceCollection
    {
        $query = BoardMeeting::query()->with('tags');

        if (($from = $request->listDateFrom()) !== null) {
            $query->whereDate('date', '>=', $from->toDateString());
        }

        if (($to = $request->listDateTo()) !== null) {
            $query->whereDate('date', '<=', $to->toDateString());
        }

        if (($status = $request->listStatus()) !== null) {
            $query->where('status', $status);
        }

        ListQuery::search($query, $request->listFilter(), self::SEARCHABLE);

        // AND-filter by tag slugs (A16, K3): meetings carrying every slug.
        app(TagService::class)->applyFilter($query, $request->listTagSlugs());

        $query->orderBy(
            ListQuery::sort($request, self::SORTABLE, 'date'),
            $request->listOrder(),
        );

        $meetings = $query->paginate(ListQuery::perPage($request, 20, 200));

        return BoardMeetingResource::collection($meetings);
    }

    public function show(Request $request, BoardMeeting $meeting): BoardMeetingResource
    {
        $meeting->load('tags');

        return (new BoardMeetingResource($meeting))
            ->additional(['letters' => $this->lettersFor($meeting)]);
    }

    public function update(UpdateBoardMeetingRequest $request, BoardMeeting $meeting): BoardMeetingResource
    {
        $meeting->update($request->validated());

        $meeting->load('tags');

        return new BoardMeetingResource($meeting);
    }

    /**
     * The meeting's invitation letters as read-only context (TCK show
     * parity): from the generic Ingest layer, never a domain table.
     *
     * @return list<array{id: int, title: ?string, date: ?string, text: ?string}>
     */
    private function lettersFor(BoardMeeting $meeting): array
    {
        return IntegrationRecord::query()
            ->forEntity('letter')
            ->where('data->has_meeting', true)
            ->whereDate('date', $meeting->date)
            ->orderBy('id')
            ->get()
            ->map(fn (IntegrationRecord $record) => [
                'id' => $record->id,
                'title' => $record->title,
                'date' => $record->date?->toDateString(),
                'text' => $record->data['text'] ?? null,
            ])
            ->all();
    }
}
