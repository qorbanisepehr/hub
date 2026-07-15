import { useQuery } from "@tanstack/react-query";
import { fetchPermissions } from "@/features/rbac/api";

export function usePermissions() {
    return useQuery({
        queryKey: ["permissions"],
        queryFn: async () => {
            const { data } = await fetchPermissions();
            return data.data;
        },
    });
}
