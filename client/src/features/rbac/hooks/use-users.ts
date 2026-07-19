import { useQuery } from "@tanstack/react-query";
import { fetchUsers } from "@/features/rbac/api";
import { userKeys } from "@/lib/query-keys";
import { PAGINATION } from "@/lib/constants";

export function useAllUsers() {
    return useQuery({
        queryKey: userKeys.allList(),
        queryFn: async () => {
            const { data } = await fetchUsers({ per_page: PAGINATION.FETCH_ALL_SIZE });
            return data.data;
        },
    });
}
