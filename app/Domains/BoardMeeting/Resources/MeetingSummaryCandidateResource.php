<?php

namespace App\Domains\BoardMeeting\Resources;

use App\Domains\BoardMeeting\Enums\MeetingSummaryAction;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One meeting in the bulk summary picker.
 *
 * Deliberately not the full meeting resource. This list exists to be read and
 * ticked, and the full payload carries the attendees, agenda and the minutes —
 * hundreds of meetings of minutes would be downloaded to render a
 * checklist, and the documents are read through their own endpoint anyway.
 * Only the two things a person identifies a meeting by are here.
 *
 * `action` is the one piece of business state the picker needs, and it is
 * stated rather than left to be inferred from an absent summary: a row the
 * person ticked may turn out to be mid-run or to hold an undecided proposal,
 * and the picker has to be able to say so instead of quietly offering work that
 * the dispatch will refuse.
 *
 * @property BoardMeeting $resource
 */
class MeetingSummaryCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'held_on' => $this->resource->held_on?->toDateString(),
            'held_on_jalali' => $this->resource->held_on_jalali,
            'registry_label' => $this->resource->registryLabel(),
            'title' => $this->resource->title,
            'action' => MeetingSummaryAction::of(
                MeetingSummary::fromArray($this->resource->summary),
            )->value,
        ];
    }
}
