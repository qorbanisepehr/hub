import { useQuery } from "@tanstack/react-query";
import { fetchAllRoles } from "@/features/rbac/api";
import { roleKeys } from "@/lib/query-keys";

export function useRoles() {
    return useQuery({
        queryKey: roleKeys.all,
        queryFn: async () => {
            const { data } = await fetchAllRoles();
            return data.data;
        },
    });
}
