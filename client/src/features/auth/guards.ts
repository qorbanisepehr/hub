import { redirect } from "@tanstack/react-router";
import { authClient } from "@/features/auth/auth-client";
import { queryClient } from "@/lib/query-client";
import { fetchMe } from "@/features/auth/api";
import type { User } from "@/features/auth/types";
import { ME_KEY } from "@/features/auth/constants";
import { hasAnyPermission } from "@/features/auth/permissions";

function getCachedUser(): User | null {
    return queryClient.getQueryData<User>(ME_KEY) ?? null;
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
        let user = getCachedUser();

        if (!user) {
            try {
                user = await queryClient.fetchQuery({
                    queryKey: ME_KEY,
                    queryFn: async () => {
                        const res = await fetchMe();
                        return res.data.data;
                    },
                    retry: false,
                });
            } catch {
                throw redirect({ to: "/login" });
            }
        }

        const names = Array.isArray(permission) ? permission : [permission];

        if (user?.is_super_admin) {
            return;
        }

        if (!hasAnyPermission(user?.permissions, names)) {
            throw redirect({ to: "/unauthorized" });
        }
    };
}

export function redirectIfAuthenticated() {
    if (authClient.isAuthenticated()) {
        throw redirect({ to: "/dashboard" });
    }
}
