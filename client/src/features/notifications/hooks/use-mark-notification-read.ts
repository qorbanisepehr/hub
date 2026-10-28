import { useMutation, useQueryClient } from "@tanstack/react-query";
import { markNotificationRead } from "../api";
import { notificationKeys } from "@/lib/query-keys";

/** Mark one notification as read, then refresh list + unread count. */
export function useMarkNotificationRead() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) => markNotificationRead(id),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: notificationKeys.all,
            });
        },
    });
}