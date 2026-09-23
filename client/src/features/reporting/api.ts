import { api } from "@/lib/api";
import type {
    AttendanceReportParams,
    AttendanceReportResponse,
    UnifiedInboxParams,
    UnifiedInboxReportResponse,
} from "./types";

export function fetchAttendanceReport(params: AttendanceReportParams) {
    return api.get<AttendanceReportResponse>("/reports/attendance", {
        params,
    });
}

export function fetchUnifiedInbox(params: UnifiedInboxParams) {
    return api.get<UnifiedInboxReportResponse>("/reports/unified-inbox", {
        params,
    });
}
