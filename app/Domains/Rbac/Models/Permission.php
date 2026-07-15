<?php

namespace App\Domains\Rbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'display_name',
        'group_id',
    ];

    /** @return BelongsTo<PermissionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class);
    }

    /** @return BelongsToMany<Role> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
