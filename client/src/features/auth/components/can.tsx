import type { ReactNode } from "react";
import { useCan } from "@/features/auth/useCan";

interface CanProps {
    permission: string;
    fallback?: ReactNode;
    children: ReactNode;
}

export function Can({ permission, fallback = null, children }: CanProps) {
    const allowed = useCan(permission);

    return <>{allowed ? children : fallback}</>;
}

interface CannotProps {
    permission: string;
    fallback?: ReactNode;
    children: ReactNode;
}

export function Cannot({ permission, fallback = null, children }: CannotProps) {
    const allowed = useCan(permission);

    return <>{allowed ? fallback : children}</>;
}
