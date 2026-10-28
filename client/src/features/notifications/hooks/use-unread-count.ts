import { useQuery } from "@tanstack/react-query";
import { fetchUnreadCount } from "../api";
import { notificationKeys } from "@/lib/query-keys";

/**
 * Backend-authoritative unread count for the header bell. Polled lightly so
 * the badge reconciles with the API over time (architecture §54/§56) —
 * realtime (Phase 3) will later tighten this further.
 */
export function useUnreadCount() {
    return useQuery({
        queryKey: notificationKeys.unreadCount(),
        queryFn: async () => {
            const { data } = await fetchUnreadCount();
            return data.data.count;
        },
        refetchInterval: 60_000,
    });
}