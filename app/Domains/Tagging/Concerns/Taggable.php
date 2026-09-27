<?php

namespace App\Domains\Tagging\Concerns;

use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Services\TagService;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Add to any model that can carry tags. Tag lifecycle (create/sync/resolve)
 * always goes through {@see TagService} —
 * this trait only declares the relation.
 */
trait Taggable
{
    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
