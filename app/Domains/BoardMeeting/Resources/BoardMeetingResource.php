<?php

namespace App\Domains\BoardMeeting\Resources;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\ArchiveListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One meeting item. `pdate` is the canonical Jalali key the archive and
 * documents are addressed by; `date` is the Gregorian equivalent. The
 * minutes payloads stay out of the list shape (detail only).
 * `archive_documents` is derived at read time from the archive disk
 * (decision 2026-09-28 — no stored documents JSONB); the client contract
 * key stays `documents` for now.
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
        $archive = app(ArchiveListing::class);

        return [
            'id' => $this->resource->id,
            'pdate' => $this->resource->pdate,
            'date' => $this->resource->date?->toDateString(),
            'number' => $this->resource->number,
            'agenda' => $this->resource->agenda,
            'documents' => $archive->forDate($this->resource->pdate),
            'meta' => (object) ($this->resource->meta ?? []),
            'synced_at' => $this->resource->synced_at?->toIso8601String(),
            'tags' => $this->resource->tags->map(fn ($tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
            ])->all(),
        ];
    }
}
