<?php

namespace App\Domains\Authorization\Services;

class PermissionRegistrar
{
    /** @var array<string, array{name: string, permissions: array<string, string>}> */
    private static array $groups = [];

    public static function registerGroup(string $slug, string $name, array $permissions): void
    {
        self::$groups[$slug] = [
            'name' => $name,
            'permissions' => $permissions,
        ];
    }

    /**
     * @return array<string, array{name: string, permissions: array<string, string>}>
     */
    public static function getRegisteredGroups(): array
    {
        return self::$groups;
    }

    public static function flush(): void
    {
        self::$groups = [];
    }

    public static function isValidPermission(string $name): bool
    {
        foreach (self::$groups as $group) {
            if (array_key_exists($name, $group['permissions'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function getAllRegisteredPermissions(): array
    {
        $permissions = [];
        foreach (self::$groups as $group) {
            $permissions = array_merge($permissions, $group['permissions']);
        }

        return $permissions;
    }
}
