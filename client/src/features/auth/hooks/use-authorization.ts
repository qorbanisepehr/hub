import { useQuery } from "@tanstack/react-query";
import { useAuth } from "@/features/auth";
import { fetchEffectivePermissions } from "@/features/auth/api";
import { authKeys } from "@/lib/query-keys";
import type { AuthorizationResponse } from "@/features/auth/types";

export function useAuthorization() {
    const { user } = useAuth();
    const activeRoleId = user?.active_role_id ?? null;

    const { data, isLoading } = useQuery({
        queryKey: [...authKeys.authorization(), activeRoleId],
        queryFn: async () => {
            const res = await fetchEffectivePermissions();
            return res.data.data as AuthorizationResponse;
        },
        enabled: !!user,
        staleTime: 60_000,
    });

    const isSuperAdmin = user?.is_super_admin ?? false;

    const can = (permission: string): boolean =>
        isSuperAdmin || (data?.permissions[permission]?.allowed ?? false);

    return {
        role: data?.role ?? null,
        permissions: data?.permissions ?? {},
        can,
        canAny: (names: string[]) => names.some(can),
        canAll: (names: string[]) => names.every(can),
        isLoading,
    };
}
