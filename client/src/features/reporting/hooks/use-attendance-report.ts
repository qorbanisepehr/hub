import { useQuery } from "@tanstack/react-query";
import { fetchAttendanceReport } from "../api";
import { reportingKeys } from "@/lib/query-keys";
import type { AttendanceReportParams } from "../types";

/**
 * Fetch the attendance report for the given window. Enabled only when both
 * bounds are present so the widget never fires a doomed request.
 */
export function useAttendanceReport(params: AttendanceReportParams) {
    const enabled = Boolean(params.from && params.to);

    return useQuery({
        queryKey: reportingKeys.attendance(params),
        queryFn: async () => {
            const { data } = await fetchAttendanceReport(params);
            return data;
        },
        enabled,
    });
}
