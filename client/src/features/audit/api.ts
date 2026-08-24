import { api } from "@/lib/api";
import type { PaginatedResponse } from "@/lib/types";
import type {
    AuditLog,
    AuditLogDetail,
    AuditLogListParams,
    AuditRetentionPolicy,
    AuditStats,
} from "./types";

export function fetchAuditLogs(params: AuditLogListParams = {}) {
    return api.get<PaginatedResponse<AuditLog>>("/audit-logs", { params });
}

export function exportAuditLogs(
    params: Omit<AuditLogListParams, "page" | "per_page"> & {
        format?: "csv" | "jsonl";
    },
) {
    return api.get("/audit-logs/export", {
        params,
        responseType: "blob",
    });
}

export function fetchAuditLog(id: number) {
    return api.get<{ data: AuditLogDetail }>(`/audit-logs/${id}`);
}

export function fetchAuditStats() {
    return api.get<{ data: AuditStats }>("/audit-logs/stats");
}

export function fetchAuditEvents(category?: string) {
    return api.get<{ data: string[] }>("/audit-logs/events", {
        params: category ? { category } : undefined,
    });
}

export function fetchRetentionPolicies() {
    return api.get<{ data: AuditRetentionPolicy[] }>(
        "/audit-retention-policies",
    );
}

export function fetchRetentionPolicy(id: number) {
    return api.get<{ data: AuditRetentionPolicy }>(
        `/audit-retention-policies/${id}`,
    );
}

export function createRetentionPolicy(
    data: Omit<AuditRetentionPolicy, "id" | "created_at" | "updated_at">,
) {
    return api.post<{ data: AuditRetentionPolicy }>(
        "/audit-retention-policies",
        data,
    );
}

export function updateRetentionPolicy(
    id: number,
    data: Partial<Omit<AuditRetentionPolicy, "id" | "created_at" | "updated_at">>,
) {
    return api.put<{ data: AuditRetentionPolicy }>(
        `/audit-retention-policies/${id}`,
        data,
    );
}

export function deleteRetentionPolicy(id: number) {
    return api.delete<{ message: string }>(`/audit-retention-policies/${id}`);
}
