import { api } from "@/lib/api";
import type { PaginatedResponse } from "@/lib/types";
import type { AppNotification, NotificationListParams } from "./types";

/** Paginated, newest-first list of the authenticated user's notifications. */
export function fetchNotifications(params: NotificationListParams = {}) {
    const { unread, ...rest } = params;

    return api.get<PaginatedResponse<AppNotification>>("/notifications", {
        params: {
            ...rest,
            // Boolean-table contract: `unread=1` / `unread=0` (FILTER_VALIDATE_BOOLEAN).
            unread: unread === undefined ? undefined : unread ? 1 : 0,
        },
    });
}

/** Backend-authoritative unread count (architecture §56). */
export function fetchUnreadCount() {
    return api.get<{ data: { count: number } }>("/notifications/unread-count");
}

/** Idempotent: marking an already-read notification is a no-op server-side. */
export function markNotificationRead(id: string) {
    return api.post<{ data: AppNotification }>(`/notifications/${id}/read`);
}

export function markAllNotificationsRead() {
    return api.post<{ data: { updated: number } }>("/notifications/read-all");
}

/** Delete every read notification of the current user (DB-level delete). */
export function deleteReadNotifications() {
    return api.post<{ data: { deleted: number } }>("/notifications/delete-read");
}