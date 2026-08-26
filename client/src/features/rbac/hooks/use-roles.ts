import { useQuery } from "@tanstack/react-query";
import { fetchAllRoles, fetchRolesChart } from "@/features/rbac/api";
import { roleKeys } from "@/lib/query-keys";

/**
 * Full roles list (plain array shape). This hook owns the
 * roleKeys.listAll() cache entry - never bind another queryFn to that key,
 * mismatched shapes were the source of `allRoles.filter is not a function`
 * crashes.
 */
export function useRoles() {
    return useQuery({
        queryKey: roleKeys.listAll(),
        queryFn: async () => {
            const { data } = await fetchAllRoles();
            return data.data;
        },
        staleTime: 5 * 60 * 1000,
    });
}

export function useRoleChart() {
    return useQuery({
        queryKey: roleKeys.chart(),
        queryFn: async () => {
            const { data } = await fetchRolesChart();
            return data.data;
        },
    });
}
