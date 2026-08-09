import { useQuery } from "@tanstack/react-query";
import { fetchAllRoles, fetchRolesChart } from "@/features/rbac/api";
import { roleKeys } from "@/lib/query-keys";

export function useRoles() {
    return useQuery({
        queryKey: roleKeys.options(),
        queryFn: async () => {
            const { data } = await fetchAllRoles();
            return data.data;
        },
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
