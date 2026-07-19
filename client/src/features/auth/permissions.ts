import type { Permission } from "@/features/rbac/types";

export function hasPermission(
    permissions: Permission[] | undefined,
    name: string,
    isSuperAdmin?: boolean,
): boolean {
    if (isSuperAdmin) return true;
    return permissions?.some((p) => p.name === name) ?? false;
}

export function hasAnyPermission(
    permissions: Permission[] | undefined,
    names: string[],
    isSuperAdmin?: boolean,
): boolean {
    if (isSuperAdmin) return true;
    return names.some((name) => hasPermission(permissions, name));
}

export function hasAllPermissions(
    permissions: Permission[] | undefined,
    names: string[],
    isSuperAdmin?: boolean,
): boolean {
    if (isSuperAdmin) return true;
    return names.every((name) => hasPermission(permissions, name));
}
