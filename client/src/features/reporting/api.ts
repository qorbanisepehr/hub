import { api } from "@/lib/api";
import type {
    AttendanceReportParams,
    AttendanceReportResponse,
    UnifiedInboxListParams,
    UnifiedInboxListResponse,
} from "./types";

export function fetchAttendanceReport(params: AttendanceReportParams) {
    return api.get<AttendanceReportResponse>("/reports/attendance", {
        params,
    });
}

export function fetchUnifiedInbox(params: UnifiedInboxListParams) {
    return api.get<UnifiedInboxListResponse>("/reports/unified-inbox", {
        params,
    });
}
