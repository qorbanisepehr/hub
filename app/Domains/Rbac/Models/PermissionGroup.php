<?php

namespace App\Domains\Rbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** @return HasMany<Permission> */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'group_id');
    }
}
