import { redirect } from "@tanstack/react-router";
import { authClient } from "@/features/auth/auth-client";
import { queryClient } from "@/lib/query-client";
import { fetchEffectivePermissions, fetchMe } from "@/features/auth/api";
import type { AuthorizationResponse, User } from "@/features/auth/types";
import { authKeys } from "@/lib/query-keys";

function getCachedUser(): User | null {
    return queryClient.getQueryData<User>(authKeys.me()) ?? null;
}

async function resolveUser(): Promise<User> {
    const cached = getCachedUser();

    if (cached) {
        return cached;
    }

    try {
        const res = await fetchMe();

        return res.data.data;
    } catch {
        throw redirect({ to: "/login" });
    }
}

async function getEffectiveAuthorization(user: User): Promise<AuthorizationResponse | null> {
    const key = [...authKeys.authorization(), user.active_role_id ?? null];
    const cached = queryClient.getQueryData<AuthorizationResponse>(key);

    if (cached) {
        return cached;
    }

    try {
        const res = await fetchEffectivePermissions();
        queryClient.setQueryData(key, res.data.data);

        return res.data.data;
    } catch {
        return null;
    }
}

export function requireAuth(location: { href: string }) {
    if (!authClient.isAuthenticated()) {
        throw redirect({
            to: "/login",
            search: { redirect: location.href },
        });
    }
}

export function requirePermission(permission: string | string[]) {
    return async () => {
        const user = await resolveUser();

        if (user?.is_super_admin) {
            return;
        }

        const names = Array.isArray(permission) ? permission : [permission];
        const authorization = await getEffectiveAuthorization(user);

        const allowed = authorization
            ? names.some((name) => authorization.permissions[name]?.allowed === true)
            : false;

        if (!allowed) {
            throw redirect({ to: "/unauthorized" });
        }
    };
}

export function redirectIfAuthenticated() {
    if (authClient.isAuthenticated()) {
        throw redirect({ to: "/dashboard" });
    }
}
