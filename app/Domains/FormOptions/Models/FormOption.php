<?php

namespace App\Domains\FormOptions\Models;

use Database\Factories\FormOptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single selectable option in a form group (e.g. group "marital_status"
 * with values "single"/"married"). The {@see group}/{@see value} pair is the
 * unique natural key used by the client forms and server validation.
 */
#[UseFactory(FormOptionFactory::class)]
class FormOption extends Model
{
    /** @use HasFactory<FormOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'group',
        'value',
        'label',
        'parent_value',
        'group_label',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
