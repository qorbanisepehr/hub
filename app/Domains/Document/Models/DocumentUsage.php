<?php

namespace App\Domains\Document\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentUsage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_id',
        'entity_type',
        'entity_id',
        'category_slug',
        'record_key',
        'slot',
        'custom_properties',
    ];

    protected $casts = [
        'custom_properties' => 'array',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
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
