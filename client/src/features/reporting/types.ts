/**
 * HTTP contract for GET /api/reports/attendance (mirrors AttendanceResource).
 * Backend accepts both calendars and tolerates Persian digits; the client
 * always sends plain Gregorian `yyyy-MM-dd`.
 */

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

export interface AttendanceWarningDto {
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
    warnings: AttendanceWarningDto[];
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
