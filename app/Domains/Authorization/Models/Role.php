<?php

namespace App\Domains\Authorization\Models;

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    public const SYSTEM_ADMINISTRATOR = 'system.administrator';

    /** @var array<string, string> */
    public const EDUCATION_LEVELS = [
        'diploma' => 'دیپلم',
        'associate' => 'فوق دیپلم',
        'bachelor' => 'لیسانس',
        'master' => 'فوق لیسانس',
        'doctorate' => 'دکتری',
    ];

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
        'parent_id',
        'matrix_managers',
        'requirements',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'matrix_managers' => 'array',
        'requirements' => 'array',
    ];

    /**
     * The direct parent (manager) role in the org hierarchy.
     *
     * @return BelongsTo<Role, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    /**
     * Roles that report directly to this role.
     *
     * @return HasMany<Role, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Role::class, 'parent_id');
    }

    /** @return HasMany<AccessRule> */
    public function accessRules(): HasMany
    {
        return $this->hasMany(AccessRule::class);
    }

    /**
     * Allow rules on this role only.
     *
     * @return HasMany<AccessRule>
     */
    public function allowedRules(): HasMany
    {
        return $this->hasMany(AccessRule::class)
            ->where('effect', AccessRuleEffect::Allow->value)
            ->where('is_active', true);
    }

    /**
     * Directly granted (allow) permissions on this role.
     *
     * @return BelongsToMany<Permission>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'access_rules', 'role_id', 'permission_id')
            ->wherePivot('effect', AccessRuleEffect::Allow->value)
            ->wherePivot('is_active', true);
    }

    /** @return BelongsToMany<User> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * Replace all access rules for this role with allow rules for the given permissions.
     *
     * @param  array<int, int>  $permissionIds
     */
    public function syncPermissions(array $permissionIds): void
    {
        DB::transaction(function () use ($permissionIds) {
            $this->accessRules()->delete();

            foreach (array_values(array_unique($permissionIds)) as $permissionId) {
                $this->accessRules()->create([
                    'permission_id' => $permissionId,
                    'effect' => AccessRuleEffect::Allow,
                    'priority' => 0,
                    'is_active' => true,
                ]);
            }
        });
    }

    /**
     * Add allow rules without touching existing rules.
     *
     * @param  array<int, int>  $permissionIds
     */
    public function grantPermissions(array $permissionIds): void
    {
        foreach (array_values(array_unique($permissionIds)) as $permissionId) {
            $this->accessRules()->updateOrCreate(
                ['permission_id' => $permissionId],
                ['effect' => AccessRuleEffect::Allow, 'is_active' => true],
            );
        }
    }

    /**
     * Replace every access rule for this role with the given rule set. Rules
     * express allow/deny effects and optional policy condition trees, so this is
     * the canonical write path for the rule builder (unlike syncPermissions,
     * which only understands plain allow rules).
     *
     * @param  array<int, array{permission_id: int, effect: string, priority?: int|null, policy?: array<mixed>|null, is_active?: bool}>  $rules
     */
    public function syncAccessRules(array $rules): void
    {
        DB::transaction(function () use ($rules) {
            $this->accessRules()->delete();

            foreach ($rules as $rule) {
                $this->accessRules()->create([
                    'permission_id' => (int) $rule['permission_id'],
                    'effect' => AccessRuleEffect::from($rule['effect']),
                    'priority' => (int) ($rule['priority'] ?? 0),
                    'policy' => isset($rule['policy']) && is_array($rule['policy']) ? $rule['policy'] : null,
                    'is_active' => (bool) ($rule['is_active'] ?? true),
                ]);
            }
        });
    }

    /**
     * Set an explicit deny rule (highest priority) for a permission.
     */
    public function denyPermission(int $permissionId): void
    {
        $this->accessRules()->updateOrCreate(
            ['permission_id' => $permissionId],
            ['effect' => AccessRuleEffect::Deny, 'priority' => 100, 'is_active' => true],
        );
    }

    /**
     * Distinct permission groups reachable through this role's allow rules.
     *
     * @return Collection<int, PermissionGroup>
     */
    public function getPermissionGroups(): Collection
    {
        $groups = $this->permissions()
            ->with('group')
            ->get()
            ->map(fn (Permission $permission) => $permission->group)
            ->filter();

        return $groups->keyBy('id')->values();
    }

    /**
     * IDs of every role this role reports to: its parent plus matrix managers.
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
            $ids[] = (int) $this->parent_id;
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
     * @param  array{education_level?: string|null, field_of_study?: string|null, related_experience_years?: int, unrelated_experience_years?: int, skills?: string[]}  $candidateData
     */
    public function meetsRequirements(array $candidateData): bool
    {
        $requirements = $this->requirements ?? [];

        $hasRequirements = isset($requirements['min_education'])
            || isset($requirements['min_related_experience_years'])
            || isset($requirements['min_unrelated_experience_years'])
            || ! empty($requirements['fields_of_study'])
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

        if (! empty($requirements['fields_of_study'])
            && ! in_array($candidateData['field_of_study'] ?? null, $requirements['fields_of_study'], true)) {
            return false;
        }

        if (isset($requirements['min_related_experience_years'])
            && ($candidateData['related_experience_years'] ?? 0) < $requirements['min_related_experience_years']) {
            return false;
        }

        if (isset($requirements['min_unrelated_experience_years'])
            && ($candidateData['unrelated_experience_years'] ?? 0) < $requirements['min_unrelated_experience_years']) {
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

    /**
     * Effective permissions for this role: allowed rules across the
     * inheritance chain, minus any explicit deny rules.
     *
     * @return Collection<int, Permission>
     */
    public function getAllPermissions(): Collection
    {
        $roleIds = $this->getInheritedRoleIds();

        $ruleQuery = AccessRule::query()
            ->whereIn('role_id', $roleIds)
            ->where('is_active', true);

        $allowedIds = (clone $ruleQuery)
            ->where('effect', AccessRuleEffect::Allow->value)
            ->pluck('permission_id');

        $deniedIds = (clone $ruleQuery)
            ->where('effect', AccessRuleEffect::Deny->value)
            ->pluck('permission_id');

        $ids = $allowedIds->diff($deniedIds);

        return Permission::whereIn('id', $ids)
            ->get()
            ->keyBy('name')
            ->values();
    }

    /**
     * Whether this role is a descendant (any depth) of the given role.
     */
    public function isChildOf(Role $role): bool
    {
        if ($role->is($this)) {
            return false;
        }

        return in_array($role->id, $this->getInheritedRoleIds(), true);
    }

    /**
     * Ids of this role and every ancestor (cycle-safe).
     *
     * @return array<int, int>
     */
    public function getInheritedRoleIds(): array
    {
        $ids = [$this->id];
        $seen = [$this->id => true];
        $parentId = $this->parent_id;

        while ($parentId !== null && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $ids[] = (int) $parentId;
            $parentId = Role::query()->whereKey($parentId)->value('parent_id');
        }

        return $ids;
    }
}
