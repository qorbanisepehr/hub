<?php

namespace App\Domains\BoardMeeting\Models;

use App\Domains\Tagging\Concerns\Taggable;
use Database\Factories\BoardMeeting\BoardMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The board meeting aggregate (A6): one meeting per Jalali date (`pdate`
 * natural key — source parity). Minutes arrive from two channels — derived
 * from letters and, when present, the source's own board form — hashed so
 * the projection can skip unchanged meetings (idempotency, TCK parity).
 * Tags ride the general Tagging service (K3).
 */
#[UseFactory(BoardMeetingFactory::class)]
final class BoardMeeting extends Model
{
    /** @use HasFactory<BoardMeetingFactory> */
    use HasFactory;

    use Taggable;

    protected $fillable = [
        'date',
        'pdate',
        'number',
        'agenda',
        'minutes',
        'minutes_from_letters',
        'minutes_hash',
        'minutes_from_heyat_modire',
        'documents',
        'meta',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'minutes_from_letters' => 'array',
            'minutes_from_heyat_modire' => 'array',
            'documents' => 'array',
            'meta' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Letter, $this> */
    public function letters(): BelongsToMany
    {
        return $this->belongsToMany(
            Letter::class,
            'bm_board_meeting_letter',
            'board_meeting_id',
            'bm_letter_id',
        );
    }
}
