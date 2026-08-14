<?php

namespace App\Domains\Authorization\Models;

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
        'matrix_managers',
        'requirements',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'matrix_managers' => 'array',
        'requirements' => 'array',
    ];

    /**
     * Roles this role inherits from (the org hierarchy).
     *
     * @return BelongsToMany<Role>
     */
    public function parentRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_inheritances', 'role_id', 'parent_role_id');
    }

    /**
     * Roles that inherit from this role.
     *
     * @return BelongsToMany<Role>
     */
    public function childRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_inheritances', 'parent_role_id', 'role_id');
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
     * IDs of every role this role reports to: parent roles plus matrix managers.
     *
     * @return array<int, int>
     */
    public function getAllManagerIds(): array
    {
        $ids = collect($this->matrix_managers ?? [])
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = array_merge($ids, $this->parentRoles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all());

        return array_values(array_unique($ids));
    }

    /**
     * First parent role id for the org chart (lowest id wins).
     */
    public function getFirstParentId(): ?int
    {
        return $this->parentRoles()->pluck('roles.id')->min() ?: null;
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
    private function getInheritedRoleIds(): array
    {
        $ids = [$this->id];
        $seen = [$this->id => true];
        $queue = [$this->id];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            $parentIds = DB::table('role_inheritances')
                ->where('role_id', $currentId)
                ->pluck('parent_role_id');

            foreach ($parentIds as $parentId) {
                $parentId = (int) $parentId;

                if (! isset($seen[$parentId])) {
                    $seen[$parentId] = true;
                    $ids[] = $parentId;
                    $queue[] = $parentId;
                }
            }
        }

        return $ids;
    }
}
