import type { ReactNode } from "react";
import { useAuth } from "@/features/auth/useAuth";
import { hasAnyPermission, hasAllPermissions } from "@/features/auth/permissions";

export function usePermission(permission: string | string[]): boolean {
    const { user } = useAuth();
    const names = Array.isArray(permission) ? permission : [permission];
    return hasAnyPermission(user?.permissions, names);
}

interface PermissionGuardProps {
    permission: string | string[];
    mode?: "any" | "all";
    children: ReactNode;
    fallback?: ReactNode;
}

export function PermissionGuard({
    permission,
    mode = "any",
    children,
    fallback = null,
}: PermissionGuardProps) {
    const { user } = useAuth();
    const names = Array.isArray(permission) ? permission : [permission];

    const allowed =
        mode === "all"
            ? hasAllPermissions(user?.permissions, names)
            : hasAnyPermission(user?.permissions, names);

    if (!allowed) {
        return <>{fallback}</>;
    }

    return <>{children}</>;
}
