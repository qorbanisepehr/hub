<?php

namespace App\Domains\BoardMeeting\Resources;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\ArchiveListing;
use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One meeting item (fresh shape, doc 060): `date` is the canonical key
 * (Gregorian; Jalali rendering is client-side). The archive listing is
 * derived at read time from the archive disk; the client contract key
 * stays `documents` until the binaries live in the Document system.
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
        $detail = $request->routeIs('board-meetings.show')
            || $request->boolean('with_detail');

        $archive = app(ArchiveListing::class);

        $payload = [
            'id' => $this->resource->id,
            'date' => $this->resource->date?->toDateString(),
            'title' => $this->resource->title,
            'status' => $this->resource->status,
            'number' => $this->resource->number,
            'documents' => $archive->forDate(
                // The archive filenames carry the Jalali meeting date.
                $this->resource->date !== null
                    ? str_replace('/', '-', PersianDateTime::toJalaliDate(CarbonImmutable::parse($this->resource->date)))
                    : '',
            ),
            'meta' => (object) ($this->resource->meta ?? []),
            'synced_at' => $this->resource->synced_at?->toIso8601String(),
            'tags' => $this->resource->tags->map(fn ($tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
            ])->all(),
        ];

        if ($detail) {
            $payload['minutes_text'] = $this->resource->minutes_text;
            $payload['agenda'] = $this->resource->agenda ?? [];
            $payload['attendees'] = $this->resource->attendees ?? [];
            $payload['summary'] = $this->resource->summary;
        }

        return $payload;
    }
}
