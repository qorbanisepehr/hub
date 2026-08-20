<?php

namespace App\Domains\Audit\Models;

use App\Domains\Authorization\Models\Role;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable audit record. Once created, never updated or deleted via business logic.
 *
 * @property int $id
 * @property string $event_id
 * @property string $event
 * @property string $category
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property int|null $actor_role_id
 * @property string|null $actor_role_name
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array|null $subject_snapshot
 * @property string|null $description
 * @property array|null $old_values
 * @property array|null $new_values
 * @property array|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $url
 * @property string|null $method
 * @property string|null $request_id
 * @property string|null $trace_id
 * @property Carbon $created_at
 */
#[UseFactory(AuditLogFactory::class)]
class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'event',
        'category',
        'actor_type',
        'actor_id',
        'actor_role_id',
        'actor_role_name',
        'subject_type',
        'subject_id',
        'subject_snapshot',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'request_id',
        'trace_id',
        'created_at',
    ];

    protected $casts = [
        'subject_snapshot' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Prevent updates — audit records are immutable.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        return false;
    }

    /**
     * Prevent deletion — audit records are immutable.
     */
    public function delete(): bool
    {
        return false;
    }

    /**
     * The role the actor was acting under.
     */
    public function actorRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'actor_role_id');
    }
}
