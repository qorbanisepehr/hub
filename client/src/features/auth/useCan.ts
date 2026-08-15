import { useAuthorization } from "@/features/auth/useAuthorization";

export function useCan(permission: string): boolean {
    const { can } = useAuthorization();

    return can(permission);
}

export function useCanAny(permissions: string[]): boolean {
    const { canAny } = useAuthorization();

    return canAny(permissions);
}

export function useCanAll(permissions: string[]): boolean {
    const { canAll } = useAuthorization();

    return canAll(permissions);
}
