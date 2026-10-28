import { useMutation, useQueryClient } from "@tanstack/react-query";
import { markAllNotificationsRead } from "../api";
import { notificationKeys } from "@/lib/query-keys";

/** Mark every unread notification as read (DB-level update server-side). */
export function useMarkAllNotificationsRead() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => markAllNotificationsRead(),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: notificationKeys.all,
            });
        },
    });
}