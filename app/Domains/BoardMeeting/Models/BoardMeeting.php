<?php

namespace App\Domains\BoardMeeting\Models;

use App\Contracts\Documentable;
use App\Domains\Tagging\Concerns\Taggable;
use App\Support\DocumentableTrait;
use Database\Factories\BoardMeeting\BoardMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The board meeting aggregate (fresh design, doc 060): TCK-parity
 * editable — the UI can update it (TCK had an update endpoint), the
 * Farzin importer creates/refreshes it (TCK created via sync only),
 * future AI agents consume it. Identity is `id`; `date` is the
 * Gregorian canonical meeting date (Jalali rendering is client-side,
 * hub convention). `number` is the registry number as plain text
 * (user decision B). Structured-but-unqueried sub-structure rides
 * JSONB per hub ADR-003: `agenda`, `attendees`, `summary` (AI seam —
 * per-field status like TCK's meta.summary), `meta`. Documents ride
 * the hub Document system (K4), tags the general Tagging service (K3).
 */
#[UseFactory(BoardMeetingFactory::class)]
final class BoardMeeting extends Model implements Documentable
{
    use DocumentableTrait;

    /** @use HasFactory<BoardMeetingFactory> */
    use HasFactory;

    use Taggable;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_HELD = 'held';

    public const STATUS_ARCHIVED = 'archived';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_HELD,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'date',
        'title',
        'status',
        'number',
        'minutes_text',
        'agenda',
        'attendees',
        'summary',
        'meta',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'agenda' => 'array',
            'attendees' => 'array',
            'summary' => 'array',
            'meta' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Meeting documents live in the hub Document system (decision K4);
     * the source-side archive listing is derived at read time from the
     * archive disk — see ArchiveListing.
     */
    public function getDocumentConfigKey(): ?string
    {
        return 'board-meeting';
    }

    public function getDocumentOwnerLabel(): ?string
    {
        return $this->date?->toDateString();
    }
}
