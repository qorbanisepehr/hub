<?php

namespace App\Domains\Rbac\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Role extends Model
{
    /** @var array<string, string> */
    public const EDUCATION_LEVELS = [
        'diploma' => 'دیپلم',
        'associate' => 'فوق دیپلم',
        'bachelor' => 'لیسانس',
        'master' => 'فوق لیسانس',
        'doctorate' => 'دکتری',
    ];

    /** @var array<string, string> */
    public const LANGUAGE_LEVELS = [
        'basic' => 'مقدماتی',
        'intermediate' => 'متوسط',
        'advanced' => 'پیشرفته',
        'native' => 'زبان مادری',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'inherits_permissions' => false,
    ];

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
        'parent_id',
        'inherits_permissions',
        'matrix_managers',
        'requirements',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'inherits_permissions' => 'boolean',
        'matrix_managers' => 'array',
        'requirements' => 'array',
    ];

    /** @return BelongsTo<Role, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    /** @return HasMany<Role> */
    public function children(): HasMany
    {
        return $this->hasMany(Role::class, 'parent_id');
    }

    /** @return BelongsToMany<Permission> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->wherePivotNotNull('permission_id');
    }

    /** @return BelongsToMany<PermissionGroup> */
    public function permissionGroups(): BelongsToMany
    {
        return $this->belongsToMany(PermissionGroup::class, 'role_permission')
            ->wherePivotNotNull('permission_group_id');
    }

    /** @return BelongsToMany<User> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * IDs of every role this role reports to: parent plus matrix managers.
     *
     * @return array<int, int>
     */
    public function getAllManagerIds(): array
    {
        $ids = collect($this->matrix_managers ?? [])
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($this->parent_id !== null) {
            $ids[] = $this->parent_id;
        }

        return array_values(array_unique($ids));
    }

    /** @return Collection<int, Role> */
    public function getMatrixManagersCollection(): Collection
    {
        $ids = collect($this->matrix_managers ?? [])
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Role::whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Whether a candidate meets this role's requirements.
     *
     * @param  array{education_level?: string|null, experience_years?: int, skills?: string[]}  $candidateData
     */
    public function meetsRequirements(array $candidateData): bool
    {
        $requirements = $this->requirements ?? [];

        $hasRequirements = isset($requirements['min_education'])
            || isset($requirements['min_experience_years'])
            || ! empty($requirements['required_skills']);

        if (! $hasRequirements) {
            return true;
        }

        if (isset($requirements['min_education'])) {
            $educationLevels = array_keys(self::EDUCATION_LEVELS);
            $requiredLevel = array_search($requirements['min_education'], $educationLevels, true);
            $candidateLevel = array_search($candidateData['education_level'] ?? '', $educationLevels, true);

            if ($requiredLevel === false || $candidateLevel === false || $candidateLevel < $requiredLevel) {
                return false;
            }
        }

        if (isset($requirements['min_experience_years'])
            && ($candidateData['experience_years'] ?? 0) < $requirements['min_experience_years']) {
            return false;
        }

        if (! empty($requirements['required_skills'])) {
            $candidateSkills = $candidateData['skills'] ?? [];

            foreach ($requirements['required_skills'] as $requiredSkill) {
                if (! in_array($requiredSkill, $candidateSkills, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return Collection<int, Permission> */
    public function getAllPermissions(): Collection
    {
        return $this->getAllPermissionsRecursive();
    }

    /**
     * @param  array<int, true>  $visited  Prevents infinite recursion on circular references.
     */
    private function getAllPermissionsRecursive(array $visited = []): Collection
    {
        if (isset($visited[$this->id])) {
            return collect();
        }

        $visited[$this->id] = true;

        $allPermissions = collect();

        if ($this->parent && $this->inherits_permissions) {
            $allPermissions = $this->parent->getAllPermissionsRecursive($visited)->keyBy('name');
        }

        foreach ($this->permissionGroups as $group) {
            $groupPermissions = $group->permissions->keyBy('name');
            $allPermissions = $allPermissions->merge($groupPermissions);
        }

        $directPermissions = $this->permissions->keyBy('name');
        $allPermissions = $allPermissions->merge($directPermissions);

        return $allPermissions->values();
    }

    public function isChildOf(Role $role): bool
    {
        $current = $this->parent;

        while ($current) {
            if ($current->id === $role->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }
}
