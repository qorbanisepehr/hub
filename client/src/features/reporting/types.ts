import type { PaginatedResponse } from "@/lib/types";

/**
 * HTTP contracts for the reporting endpoints (mirror the API resources).
 * Backend accepts both calendars and tolerates Persian digits; the client
 * always sends plain Gregorian `yyyy-MM-dd`.
 */

/* ---------------------------------- Attendance --------------------------------- */

export type AttendanceFreshness = "live" | "cached" | "snapshot" | "synchronized";

export interface AttendanceRecordDto {
    employee_identifier: string;
    /** Gregorian `yyyy-MM-dd` (backend canonical form). */
    date: string;
    /** ISO 8601 or null when no entry was recorded. */
    entry_at: string | null;
    /** ISO 8601 or null when no exit was recorded. */
    exit_at: string | null;
    worked_minutes: number | null;
}

export interface ReportWarningDto {
    source?: string;
    code?: string;
    [key: string]: unknown;
}

export interface AttendanceReportResponse {
    data: AttendanceRecordDto[];
    report: string;
    generated_at: string;
    freshness: AttendanceFreshness | string;
    metadata: Record<string, unknown>;
    warnings: ReportWarningDto[];
}

/**
 * Type alias (not interface) so the object is directly assignable to the
 * `Record<string, unknown>` query-key parameter without casts.
 */
export type AttendanceReportParams = {
    /** Gregorian `yyyy-MM-dd`. */
    from: string;
    /** Gregorian `yyyy-MM-dd`. */
    to: string;
    status?: string;
};

/* -------------------------------- Unified Inbox -------------------------------- */

export type UnifiedInboxStatus = "unseen" | "seen" | "done";

/**
 * Sender block of a unified inbox item: matched = resolved to an HR Hub
 * user by username (render avatar + name + org role); otherwise the
 * external name is shown with a generic badge.
 */
export interface SenderDto {
    matched: boolean;
    display_name: string;
    username: string | null;
    avatar_url: string | null;
    org_role: string | null;
    user_id: number | null;
    external_name: string | null;
}

export interface UnifiedInboxItemDto {
    source: string;
    sender: SenderDto;
    document_number: string;
    title: string;
    document_type: string;
    /** ISO 8601. */
    received_at: string;
    /** Unified cartable bucket: action_required | signature | information. */
    cartable_action: string;
    status: UnifiedInboxStatus | string;
    /** "system:externalId" origin identity. */
    external_reference: string;
    external_url: string | null;
}

/**
 * The standard list envelope (`PaginatedResponse`) plus the report-owned
 * diagnostics the cartable header shows: per-source status, freshness,
 * warnings.
 */
export type UnifiedInboxListResponse = PaginatedResponse<UnifiedInboxItemDto> & {
    report: string;
    generated_at: string;
    /** source key → "success" | "unavailable". */
    sources: Record<string, string>;
    metadata: Record<string, unknown>;
    warnings: ReportWarningDto[];
};

/**
 * Standard list params — the same contract employees/audit speak. Single
 * value + `_not` exclusion per column filter, audit-style date window.
 */
export interface UnifiedInboxListParams {
    page?: number;
    per_page?: number;
    /** Whitelisted sort column (`received_at`). */
    sort?: string;
    order?: "asc" | "desc";
    source?: string;
    source_not?: string;
    type?: string;
    type_not?: string;
    status?: UnifiedInboxStatus | string;
    status_not?: string;
    /** Gregorian `yyyy-MM-dd` (client always sends Gregorian). */
    date_from?: string;
    date_to?: string;
    /** Global free-text search needle (title/number/sender). */
    filter?: string;
}
