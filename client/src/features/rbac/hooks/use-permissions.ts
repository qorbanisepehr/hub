import { useQuery } from "@tanstack/react-query";
import { fetchPermissions } from "@/features/rbac/api";
import { permissionKeys } from "@/lib/query-keys";

export function usePermissions() {
    return useQuery({
        queryKey: permissionKeys.all,
        queryFn: async () => {
            const { data } = await fetchPermissions();
            return data.data;
        },
    });
}
