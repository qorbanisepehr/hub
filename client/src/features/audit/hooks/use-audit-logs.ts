import { useQuery } from "@tanstack/react-query";
import { fetchAuditLogs } from "../api";
import { auditKeys } from "@/lib/query-keys";
import type { AuditLogListParams } from "../types";

export function useAuditLogs(params: AuditLogListParams) {
    return useQuery({
        queryKey: auditKeys.logList(params as Record<string, unknown>),
        queryFn: async () => {
            const { data } = await fetchAuditLogs(params);
            return data;
        },
    });
}
