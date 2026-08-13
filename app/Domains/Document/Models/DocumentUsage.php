<?php

namespace App\Domains\Document\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class DocumentUsage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_id',
        'entity_type',
        'entity_id',
        'section_key',
        'field_key',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * A field placement always implies a section. Mirror of the database
     * constraint (field_key != null ⇒ section_key != null), enforced for every
     * driver — including SQLite, which cannot add CHECKs via ALTER TABLE.
     */
    protected static function booted(): void
    {
        static::saving(function (DocumentUsage $usage): void {
            if ($usage->field_key !== null && ($usage->section_key === null || $usage->section_key === '')) {
                throw new InvalidArgumentException('A document field placement requires a section_key.');
            }
        });
    }

    /**
     * Resolve the entity model from morph type + id.
     */
    public function resolveEntity(): ?Model
    {
        $class = $this->entity_type;
        if (! class_exists($class)) {
            return null;
        }

        return $class::find($this->entity_id);
    }
}
