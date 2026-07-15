<?php

namespace App\Providers;

use App\Domains\Rbac\Services\PermissionRegistrar;
use Illuminate\Support\ServiceProvider;

class PermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $groups = config('permissions.groups', []);

        foreach ($groups as $slug => $group) {
            if (! isset($group['name'], $group['permissions'])) {
                throw new \InvalidArgumentException(
                    "Permission group [{$slug}] must define 'name' and 'permissions' keys.",
                );
            }

            PermissionRegistrar::registerGroup(
                slug: $slug,
                name: $group['name'],
                permissions: $group['permissions'],
            );
        }
    }
}
