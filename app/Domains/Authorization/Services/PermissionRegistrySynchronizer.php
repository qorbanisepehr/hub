<?php

namespace App\Domains\Authorization\Services;

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;

/**
 * Synchronizes the permission registry (declared in code via PermissionRegistrar)
 * with the permissions tables. Used by the seeder and the authorization:sync
 * command so there is a single source of truth for group/permission upserts.
 */
final class PermissionRegistrySynchronizer
{
    /**
     * @return array{
     *     groups_created: array<int, string>,
     *     groups_updated: array<int, string>,
     *     permissions_created: array<int, string>,
     *     permissions_updated: array<int, string>,
     *     pruned_groups: array<int, string>,
     *     pruned_permissions: array<int, string>,
     *     permission_models: array<string, Permission>,
     *     group_permission_ids: array<string, array<int, int>>,
     * }
     */
    public function sync(bool $dryRun = false, bool $prune = false): array
    {
        $groups = PermissionRegistrar::getRegisteredGroups();
        $this->validate($groups);

        $result = [
            'groups_created' => [],
            'groups_updated' => [],
            'permissions_created' => [],
            'permissions_updated' => [],
            'pruned_groups' => [],
            'pruned_permissions' => [],
            'permission_models' => [],
            'group_permission_ids' => [],
        ];

        $sortOrder = 0;

        foreach ($groups as $slug => $group) {
            $groupModel = $this->syncGroup($slug, $group, $sortOrder++, $dryRun, $result);
            $groupPermissionIds = [];

            foreach ($group['permissions'] as $name => $label) {
                $permissionModel = $this->syncPermission($name, $label, $slug, $groupModel, $dryRun, $result);

                if ($permissionModel->exists) {
                    $groupPermissionIds[] = $permissionModel->id;
                }

                $result['permission_models'][$name] = $permissionModel;
            }

            $result['group_permission_ids'][$slug] = $groupPermissionIds;
        }

        if ($prune && ! $dryRun) {
            $this->prune($groups, $result);
        }

        return $result;
    }

    /**
     * @param  array{name: string, permissions: array<string, string>}  $group
     * @param  array<string, mixed>  $result
     */
    private function syncGroup(string $slug, array $group, int $sortOrder, bool $dryRun, array &$result): PermissionGroup
    {
        $existing = PermissionGroup::where('slug', $slug)->first();
        $changed = $existing === null
            || $existing->name !== $group['name']
            || (int) $existing->sort_order !== $sortOrder;

        if ($dryRun) {
            if ($existing === null) {
                $result['groups_created'][] = $slug;
            } elseif ($changed) {
                $result['groups_updated'][] = $slug;
            }

            return $existing ?? new PermissionGroup(['slug' => $slug]);
        }

        if ($existing === null || $changed) {
            $existing = PermissionGroup::updateOrCreate(
                ['slug' => $slug],
                ['name' => $group['name'], 'sort_order' => $sortOrder],
            );
        }

        if ($existing->wasRecentlyCreated) {
            $result['groups_created'][] = $slug;
        } elseif ($changed) {
            $result['groups_updated'][] = $slug;
        }

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function syncPermission(
        string $name,
        string $label,
        string $resource,
        PermissionGroup $group,
        bool $dryRun,
        array &$result,
    ): Permission {
        $action = $this->actionOf($name);
        $existing = Permission::where('name', $name)->first();
        $changed = $existing === null
            || $existing->display_name !== $label
            || $existing->group_id !== $group->id
            || $existing->resource !== $resource
            || $existing->action !== $action
            || $existing->is_active !== true;

        if ($dryRun) {
            if ($existing === null) {
                $result['permissions_created'][] = $name;
            } elseif ($changed) {
                $result['permissions_updated'][] = $name;
            }

            return $existing ?? new Permission(['name' => $name]);
        }

        if ($existing === null || $changed) {
            $existing = Permission::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $label,
                    'label' => $label,
                    'group_id' => $group->id,
                    'resource' => $resource,
                    'action' => $action,
                    'is_active' => true,
                ],
            );
        }

        if ($existing->wasRecentlyCreated) {
            $result['permissions_created'][] = $name;
        } elseif ($changed) {
            $result['permissions_updated'][] = $name;
        }

        return $existing;
    }

    /**
     * @param  array<string, array{name: string, permissions: array<string, string>}>  $registeredGroups
     * @param  array<string, mixed>  $result
     */
    private function prune(array $registeredGroups, array &$result): void
    {
        $registeredSlugs = array_keys($registeredGroups);
        $registeredNames = collect($registeredGroups)
            ->flatMap(fn (array $group) => array_keys($group['permissions']))
            ->all();

        $staleGroups = PermissionGroup::whereNotIn('slug', $registeredSlugs)->get();

        foreach ($staleGroups as $group) {
            $result['pruned_groups'][] = $group->slug;
            $group->delete();
        }

        $registeredGroupIds = PermissionGroup::whereIn('slug', $registeredSlugs)->pluck('id')->all();

        $stalePermissions = Permission::whereNotIn('name', $registeredNames)
            ->whereIn('group_id', $registeredGroupIds)
            ->get();

        foreach ($stalePermissions as $permission) {
            $result['pruned_permissions'][] = $permission->name;
            $permission->delete();
        }
    }

    /**
     * @param  array<string, array{name: string, permissions: array<string, string>}>  $groups
     */
    private function validate(array $groups): void
    {
        foreach ($groups as $slug => $group) {
            if (! isset($group['name'], $group['permissions'])) {
                throw new \InvalidArgumentException(
                    "Permission group [{$slug}] must define 'name' and 'permissions' keys.",
                );
            }

            foreach (array_keys($group['permissions']) as $name) {
                if (! is_string($name) || $name === '' || ! str_contains($name, '.')) {
                    throw new \InvalidArgumentException(
                        "Invalid permission name [{$name}] in group [{$slug}]. Expected 'resource.action'.",
                    );
                }

                if (str_starts_with($name, $slug.'.') === false) {
                    throw new \InvalidArgumentException(
                        "Permission [{$name}] must be prefixed with its group slug [{$slug}].",
                    );
                }
            }
        }
    }

    private function actionOf(string $name): string
    {
        return explode('.', $name, 2)[1] ?? '';
    }
}
