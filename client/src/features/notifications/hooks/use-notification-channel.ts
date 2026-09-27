import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/features/auth/hooks/use-auth";
import { getEcho } from "@/lib/echo";
import { notificationKeys } from "@/lib/query-keys";

/**
 * Realtime refresh for the notification center (architecture §29/§30).
 *
 * Subscribes the signed-in user to their private channel
 * (users.{id}.notifications) and, on every `notification.created` broadcast,
 * invalidates the notification queries so the bell refetches from the API —
 * which remains the source of truth. When Reverb is not configured,
 * `getEcho()` returns null and the 60s poll keeps working (§56).
 */
export function useNotificationChannel() {
    const { user } = useAuth();
    const queryClient = useQueryClient();

    useEffect(() => {
        if (!user) {
            return;
        }

        const echo = getEcho();
        if (!echo) {
            return;
        }

        const channel = echo.private(`users.${user.id}.notifications`);

        channel.listen(".notification.created", () => {
            void queryClient.invalidateQueries({
                queryKey: notificationKeys.all,
            });
        });

        return () => {
            channel.stopListening(".notification.created");
            echo.leaveChannel(channel.name);
        };
    }, [user, queryClient]);
}