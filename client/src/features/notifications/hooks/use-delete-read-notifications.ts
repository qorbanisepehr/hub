import { useMutation, useQueryClient } from "@tanstack/react-query";
import { deleteReadNotifications } from "../api";
import { notificationKeys } from "@/lib/query-keys";

/** Delete every read notification (DB-level delete server-side). */
export function useDeleteReadNotifications() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => deleteReadNotifications(),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: notificationKeys.all,
            });
        },
    });
}