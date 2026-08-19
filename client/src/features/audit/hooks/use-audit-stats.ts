import { useQuery } from "@tanstack/react-query";
import { fetchAuditStats } from "../api";
import { auditKeys } from "@/lib/query-keys";

export function useAuditStats() {
    return useQuery({
        queryKey: auditKeys.stats(),
        queryFn: () => fetchAuditStats(),
    });
}
