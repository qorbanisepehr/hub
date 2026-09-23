import { api } from "@/lib/api";
import type { AttendanceReportParams, AttendanceReportResponse } from "./types";

export function fetchAttendanceReport(params: AttendanceReportParams) {
    return api.get<AttendanceReportResponse>("/reports/attendance", {
        params,
    });
}
