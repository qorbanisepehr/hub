import { useQuery } from "@tanstack/react-query";

import { fetchBoardMeeting, fetchBoardMeetings } from "../api";
import { boardMeetingKeys } from "@/lib/query-keys";
import type { BoardMeetingListParams } from "../types";

/**
 * Server-paginated board meeting list. The params object is the URL-driven
 * state produced by `useDataTableUrlState` — page/per_page/sort/order plus
 * the optional date window — so the query key changes exactly when the URL
 * does and the shared pagination footer drives the server.
 */
export function useBoardMeetings(params: BoardMeetingListParams) {
    return useQuery({
        queryKey: boardMeetingKeys.list(params as Record<string, unknown>),
        queryFn: async () => {
            const { data } = await fetchBoardMeetings(params);
            return data;
        },
    });
}

export function useBoardMeeting(id: number | string) {
    return useQuery({
        queryKey: boardMeetingKeys.detail(id),
        queryFn: async () => {
            const { data } = await fetchBoardMeeting(id);
            return data;
        },
    });
}
