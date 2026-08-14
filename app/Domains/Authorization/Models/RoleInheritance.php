<?php

namespace App\Domains\Authorization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleInheritance extends Model
{
    protected $fillable = [
        'role_id',
        'parent_role_id',
    ];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function parentRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_role_id');
    }
}
