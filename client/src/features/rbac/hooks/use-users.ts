import { useQuery } from "@tanstack/react-query";
import { fetchUsers } from "@/features/rbac/api";

export function useAllUsers() {
    return useQuery({
        queryKey: ["users", "all"],
        queryFn: async () => {
            const { data } = await fetchUsers({ per_page: 100 });
            return data.data;
        },
    });
}
