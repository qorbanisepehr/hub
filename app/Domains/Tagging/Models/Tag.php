<?php

namespace App\Domains\Tagging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A general-purpose tag (K3 = shared tagging service). Polymorphic via the
 * `taggables` pivot so any domain can attach tags without schema changes.
 * `name`/`slug` are unique natural keys, TCK-compatible for the archive
 * import (K6).
 */
class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    /**
     * Attachments across every taggable type. A plain pivot-model relation
     * (not a MorphToMany, which would constrain taggable_type to Tag) so
     * `withCount('taggables')` reports true usage counts.
     */
    public function taggables(): HasMany
    {
        return $this->hasMany(TaggablePivot::class);
    }

    public function scopeSlugs($query, array $slugs)
    {
        return $query->whereIn('slug', $slugs);
    }
}
