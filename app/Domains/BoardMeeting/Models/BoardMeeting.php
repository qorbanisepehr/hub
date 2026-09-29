<?php

namespace App\Domains\BoardMeeting\Models;

use App\Casts\AgendaItemListCast;
use App\Casts\ApprovalListCast;
use App\Casts\AttendeeListCast;
use App\Contracts\Documentable;
use App\Domains\Tagging\Concerns\Taggable;
use App\Support\DocumentableTrait;
use Database\Factories\BoardMeeting\BoardMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A board meeting in the registry (ADR-011).
 *
 * Identity is the registry key — `held_on_jalali` + `sequence` — not the
 * date: a meeting is not a date, and one day can hold an extraordinary
 * session. `held_on` stays the Gregorian canonical date every other hub
 * domain uses; `held_on_jalali` is the key the archive files the meeting
 * under, not a second copy of the same value.
 *
 * Ownership is split by column, and the split is enforced here rather
 * than by convention: {@see HUMAN_COLUMNS} are written by people, and
 * the first such write stamps `source_locked_at` so a later re-import
 * can never revert it. The rest of the registry columns belong to the
 * source and are refreshed by `BoardMeetingRegistry::import()`.
 *
 * Two columns belong to neither side, and the registry's input contract
 * simply does not carry them: `approvals` arrives once with the archive
 * migration and afterwards only the Document system links to it
 * (ADR-011 decision 9); `summary` is the AI job's. Both are deliberately
 * outside the lock — a summary must regenerate on a locked row, and no
 * sync may empty a list of approved numbers.
 */
#[UseFactory(BoardMeetingFactory::class)]
final class BoardMeeting extends Model implements Documentable
{
    use DocumentableTrait;

    /** @use HasFactory<BoardMeetingFactory> */
    use HasFactory;

    use Taggable;

    /**
     * Columns a human owns. A dirty write to any of these on an existing
     * row is what freezes the source's view of the meeting.
     *
     * @var list<string>
     */
    public const HUMAN_COLUMNS = ['title', 'minutes', 'attendees'];

    protected $fillable = [
        'held_on',
        'held_on_jalali',
        'sequence',
        'title',
        'minutes',
        'approvals',
        'agenda',
        'attendees',
        'summary',
        'origin',
        'provenance',
        'imported_at',
        'source_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'sequence' => 'integer',
            'approvals' => ApprovalListCast::class,
            'agenda' => AgendaItemListCast::class,
            'attendees' => AttendeeListCast::class,
            'provenance' => 'array',
            'summary' => 'array',
            'imported_at' => 'datetime',
            'source_locked_at' => 'datetime',
        ];
    }

    /**
     * Freeze the source's ownership the moment a person edits the row.
     * Creation is exempt: an import seeds `minutes`/`attendees` on the
     * row it creates, and that seed is not a human edit.
     */
    protected static function booted(): void
    {
        self::saving(function (self $meeting): void {
            if ($meeting->exists
                && $meeting->source_locked_at === null
                && $meeting->isDirty(self::HUMAN_COLUMNS)) {
                $meeting->source_locked_at = now();
            }
        });
    }

    /** Whether a human has taken over this row. */
    public function isSourceLocked(): bool
    {
        return $this->source_locked_at !== null;
    }

    /**
     * The registry label, e.g. `1404/06/15 — جلسه 2`; the jalali date is
     * stored in `yyyy-MM-dd` form, so the slashes are presentation.
     */
    public function registryLabel(): string
    {
        $date = str_replace('-', '/', $this->held_on_jalali ?? '');

        return $this->sequence > 1
            ? sprintf('%s — جلسه %d', $date, $this->sequence)
            : $date;
    }

    public function getDocumentConfigKey(): ?string
    {
        return 'board-meeting';
    }

    public function getDocumentOwnerLabel(): ?string
    {
        return $this->held_on?->toDateString();
    }
}
