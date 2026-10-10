<?php

namespace App\Domains\BoardMeeting\Resources;

use App\Domains\BoardMeeting\Models\BoardMeeting;
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
        ];
    }
}
