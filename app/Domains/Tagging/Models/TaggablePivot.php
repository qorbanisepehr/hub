<?php

namespace App\Domains\Tagging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row on the `taggables` pivot (one tag attached to one taggable entity).
 * Exposed as a plain model so {@see Tag::taggables()} can count usage across
 * every taggable type at once — the per-type inverse relations live on the
 * taggable models themselves (`morphedByMany(..., 'taggable')`).
 *
 * @property int $tag_id
 * @property class-string $taggable_type
 * @property int $taggable_id
 */
class TaggablePivot extends Model
{
    protected $table = 'taggables';

    protected $fillable = ['tag_id', 'taggable_type', 'taggable_id'];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
