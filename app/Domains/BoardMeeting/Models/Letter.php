<?php

namespace App\Domains\BoardMeeting\Models;

use Database\Factories\BoardMeeting\LetterFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An internal letter (A5/K5): sync-fed from the Farzin source through the
 * staging pipeline and strictly read-only (C6) — no UI, no manual CRUD.
 * It exists to build meetings; cleaned text lives in real columns, the
 * untouched source row in `original` (JSONB).
 */
#[UseFactory(LetterFactory::class)]
final class Letter extends Model
{
    /** @use HasFactory<LetterFactory> */
    use HasFactory;

    protected $table = 'bm_letters';

    public $timestamps = false;

    protected $fillable = [
        'source_id',
        'subject',
        'text',
        'date',
        'pdate',
        'entity_number',
        'export_entity_number',
        'r_title',
        'r_name',
        'company_name',
        'meeting_date',
        'meeting_pdate',
        'has_meeting',
        'creator_role_id',
        'original',
        'source_last_edit_date',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'meeting_date' => 'date',
            'has_meeting' => 'boolean',
            'original' => 'array',
            'source_last_edit_date' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
