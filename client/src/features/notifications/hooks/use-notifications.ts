import { useQuery } from "@tanstack/react-query";
import { fetchNotifications } from "../api";
import { notificationKeys } from "@/lib/query-keys";
import type { NotificationListParams } from "../types";

export function useNotifications(params: NotificationListParams) {
    return useQuery({
        queryKey: notificationKeys.list(params as Record<string, unknown>),
        queryFn: async () => {
            const { data } = await fetchNotifications(params);
            return data;
        },
    });
}