import { api } from "@/lib/api";
import type {
    AttendanceListParams,
    AttendanceListResponse,
    UnifiedInboxListParams,
    UnifiedInboxListResponse,
} from "./types";

export function fetchAttendanceReport(params: AttendanceListParams) {
    return api.get<AttendanceListResponse>("/reports/attendance", {
        params,
    });
}

export function fetchUnifiedInbox(params: UnifiedInboxListParams) {
    return api.get<UnifiedInboxListResponse>("/reports/unified-inbox", {
        params,
    });
}
