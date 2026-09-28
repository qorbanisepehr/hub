import type { AppNotification } from "./types";

/** Resource reference carried by the broadcast payload (architecture §25). */
type NotificationResource = { type?: string; id?: string | number };

/**
 * Deep link for a notification's resource. Only real destinations are mapped;
 * notifications without a dedicated destination (e.g. failed-login alerts)
 * return `null` so callers can fall back to the notification center instead of
 * guessing a route (architecture §53).
 */
export function notificationHref(notification: AppNotification): string | null {
    const resource = notification.data.resource as
        | NotificationResource
        | undefined;

    if (resource?.type === "questionnaire" && resource.id != null) {
        return `/questionnaires/${resource.id}`;
    }

    return null;
}