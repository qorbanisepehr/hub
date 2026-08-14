<?php

namespace App\Domains\Authorization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'resource',
        'action',
        'label',
        'metadata',
        'is_active',
        'group_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<PermissionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class);
    }

    /** @return BelongsToMany<Role> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'access_rules')
            ->wherePivot('effect', 'allow');
    }
}
