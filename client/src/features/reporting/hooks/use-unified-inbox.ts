import { useQuery } from "@tanstack/react-query";
import { fetchUnifiedInbox } from "../api";
import { reportingKeys } from "@/lib/query-keys";
import type { UnifiedInboxListParams } from "../types";

/**
 * Server-paginated cartable query. The params object is the URL-driven
 * state produced by `useDataTableUrlState` — page/per_page/sort/order plus
 * the single-value filter contract — so the query key changes exactly when
 * the URL does and the shared pagination footer drives the server.
 */
export function useUnifiedInbox(params: UnifiedInboxListParams) {
    return useQuery({
        queryKey: reportingKeys.unifiedInbox(params as Record<string, unknown>),
        queryFn: async () => {
            const { data } = await fetchUnifiedInbox(params);
            return data;
        },
    });
}
