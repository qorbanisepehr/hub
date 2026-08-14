<?php

namespace App\Domains\Authorization\Models;

use App\Domains\Authorization\Enums\AccessRuleEffect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRule extends Model
{
    protected $fillable = [
        'role_id',
        'permission_id',
        'effect',
        'priority',
        'policy',
        'is_active',
    ];

    protected $casts = [
        'effect' => AccessRuleEffect::class,
        'priority' => 'integer',
        'policy' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
