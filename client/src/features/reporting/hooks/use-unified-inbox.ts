import { useQuery } from "@tanstack/react-query";
import { fetchUnifiedInbox } from "../api";
import { reportingKeys } from "@/lib/query-keys";
import type { UnifiedInboxParams } from "../types";

/**
 * Fetch the unified inbox (کارتابل). Open-ended by default: when no window
 * is given the sources serve their own recent look-back (plan §41 — a live
 * work list, not a historical query). Array filters serialize as repeated
 * `sources[]`/`statuses[]` keys, matching the backend FormRequest.
 */
export function useUnifiedInbox(params: UnifiedInboxParams) {
    return useQuery({
        queryKey: reportingKeys.unifiedInbox(params as Record<string, unknown>),
        queryFn: async () => {
            const { data } = await fetchUnifiedInbox(params);
            return data;
        },
    });
}
