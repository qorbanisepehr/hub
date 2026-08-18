import type { ReactNode } from "react";
import { useAuthorization } from "@/features/auth";
import { usePermission } from "@/features/auth/hooks/use-permission";

export { usePermission };

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
    const { canAny, canAll } = useAuthorization();
    const names = Array.isArray(permission) ? permission : [permission];

    const allowed = mode === "all" ? canAll(names) : canAny(names);

    if (!allowed) {
        return <>{fallback}</>;
    }

    return <>{children}</>;
}
