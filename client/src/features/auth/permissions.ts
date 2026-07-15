import type { Permission } from "@/features/rbac/types";

export function hasPermission(
    permissions: Permission[] | undefined,
    name: string,
): boolean {
    return permissions?.some((p) => p.name === name) ?? false;
}

export function hasAnyPermission(
    permissions: Permission[] | undefined,
    names: string[],
): boolean {
    return names.some((name) => hasPermission(permissions, name));
}

export function hasAllPermissions(
    permissions: Permission[] | undefined,
    names: string[],
): boolean {
    return names.every((name) => hasPermission(permissions, name));
}
