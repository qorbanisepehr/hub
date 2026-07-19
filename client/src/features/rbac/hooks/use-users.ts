import { useQuery } from "@tanstack/react-query";
import { fetchUsers } from "@/features/rbac/api";
import { userKeys } from "@/lib/query-keys";

export function useAllUsers() {
    return useQuery({
        queryKey: userKeys.allList(),
        queryFn: async () => {
            const { data } = await fetchUsers({ per_page: 100 });
            return data.data;
        },
    });
}
