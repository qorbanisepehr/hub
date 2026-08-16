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

    /**
     * The authorization resource type whose attributes can scope a rule for
     * this permission. Group slugs are not always the attribute resource key
     * (e.g. `employee.documents` scopes on `document_usage`), so the slug is
     * normalized here instead of in the policy builder.
     */
    public function policyResourceType(): ?string
    {
        $resource = mb_strtolower((string) $this->resource);

        return match ($resource) {
            'employee.documents', 'cv.documents', 'questionnaire.documents' => 'document_usage',
            'document-category' => 'document_category',
            'form-options' => 'form_option',
            '' => null,
            default => str_replace('-', '_', $resource),
        };
    }
}
