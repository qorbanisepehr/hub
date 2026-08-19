import { useQuery } from "@tanstack/react-query";
import { fetchAuditLog } from "../api";
import { auditKeys } from "@/lib/query-keys";

export function useAuditLogDetail(id: number) {
    return useQuery({
        queryKey: auditKeys.logDetail(id),
        queryFn: () => fetchAuditLog(id),
    });
}
