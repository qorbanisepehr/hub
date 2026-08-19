<?php

namespace App\Domains\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Retention policy for audit records.
 * Policies are resolved in order: exact event → category → default.
 *
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property string|null $event
 * @property int $retention_days
 * @property int|null $archive_after_days
 * @property bool $archive_enabled
 * @property bool $delete_after_archive
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AuditRetentionPolicy extends Model
{
    protected $fillable = [
        'name',
        'category',
        'event',
        'retention_days',
        'archive_after_days',
        'archive_enabled',
        'delete_after_archive',
        'is_active',
    ];

    protected $casts = [
        'archive_enabled' => 'boolean',
        'delete_after_archive' => 'boolean',
        'is_active' => 'boolean',
    ];
}
