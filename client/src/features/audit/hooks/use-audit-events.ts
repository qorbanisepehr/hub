import { useQuery } from "@tanstack/react-query";
import { fetchAuditEvents } from "../api";
import { auditKeys } from "@/lib/query-keys";

export function useAuditEvents(category?: string) {
    return useQuery({
        queryKey: [...auditKeys.all, "events", { category }] as const,
        queryFn: async () => {
            const { data } = await fetchAuditEvents(category);
            return data;
        },
    });
}
