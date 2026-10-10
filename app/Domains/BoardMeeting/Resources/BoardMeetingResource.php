<?php

namespace App\Domains\BoardMeeting\Resources;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One meeting item. Serialization only: the jalali registry key is
 * already a column, the typed lists already came out of their casts, and
 * documents are read through the hub Document system — so a listing of
 * twenty meetings costs no disk scan and no calendar conversion.
 *
 * @property BoardMeeting $resource
 */
class BoardMeetingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The two summary routes answer with the meeting, and what they changed
        // is the summary — so a client that just asked for a run or just decided
        // one has to get the summary back, or it has nothing to render. Listing
        // the routes rather than a flag keeps that from depending on the caller
        // remembering to ask.
        $detail = $request->routeIs('board-meetings.show', 'board-meetings.summary.*')
            || $request->boolean('with_detail');

        $payload = [
            'id' => $this->resource->id,
            'held_on' => $this->resource->held_on?->toDateString(),
            'held_on_jalali' => $this->resource->held_on_jalali,
            'registry_label' => $this->resource->registryLabel(),
            'sequence' => $this->resource->sequence,
            'title' => $this->resource->title,
            'attendee_count' => count($this->resource->attendees),
            'tags' => $this->resource->tags->map(fn ($tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
            ])->all(),
            'origin' => $this->resource->origin,
            'imported_at' => $this->resource->imported_at?->toIso8601String(),
            'source_locked_at' => $this->resource->source_locked_at?->toIso8601String(),
            'source_locked' => $this->resource->isSourceLocked(),
        ];

        if ($detail) {
            $payload['minutes'] = $this->resource->minutes;
            $payload['agenda'] = array_map(
                fn (AgendaItem $item) => $item->toArray(),
                $this->resource->agenda,
            );
            $payload['attendees'] = array_map(
                fn (Attendee $attendee) => $attendee->toArray(),
                $this->resource->attendees,
            );
            $payload['provenance'] = (object) ($this->resource->provenance ?? []);
            $payload['summary'] = $this->resource->summary;
        }

        return $payload;
    }
}
