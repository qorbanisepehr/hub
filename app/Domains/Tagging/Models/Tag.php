<?php

namespace App\Domains\Tagging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

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
     * Domains declare their own `tags(): MorphToMany` using
     * `morphedByMany(..., 'taggable')`; these reverse relations are kept
     * here only for pivot integrity helpers and tests.
     */
    public function taggables(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeSlugs($query, array $slugs)
    {
        return $query->whereIn('slug', $slugs);
    }
}
