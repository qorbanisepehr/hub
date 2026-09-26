import { useQuery } from "@tanstack/react-query";
import { fetchAttendanceReport } from "../api";
import { reportingKeys } from "@/lib/query-keys";
import type { AttendanceListParams } from "../types";

/**
 * Server-paginated attendance query. The params object is the URL-driven
 * state produced by `useDataTableUrlState` — the window is required (the
 * report is historical, not a live work list) so the hook only fires when
 * both bounds are present.
 */
export function useAttendanceReport(params: AttendanceListParams) {
    const enabled = Boolean(params.date_from && params.date_to);

    return useQuery({
        queryKey: reportingKeys.attendance(
            params as unknown as Record<string, unknown>,
        ),
        queryFn: async () => {
            const { data } = await fetchAttendanceReport(params);
            return data;
        },
        enabled,
    });
}
