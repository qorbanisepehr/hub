import { useQuery } from "@tanstack/react-query";
import { fetchAllRoles } from "@/features/rbac/api";

export function useRoles() {
    return useQuery({
        queryKey: ["roles"],
        queryFn: async () => {
            const { data } = await fetchAllRoles();
            return data.data;
        },
    });
}
