import { useAuthorization } from "./use-authorization";

/**
 * Check if the current user has any of the given permissions.
 * Supports single permission or array of permissions (OR logic).
 */
export function usePermission(permission: string | string[]): boolean {
    const { canAny } = useAuthorization();
    const names = Array.isArray(permission) ? permission : [permission];

    return canAny(names);
}
