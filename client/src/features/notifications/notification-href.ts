import type { AppNotification } from "./types";

/** Resource reference carried by the broadcast payload (architecture §25). */
type NotificationResource = {
    type?: string;
    id?: string | number;
    /** What the reader is being sent there to do, when a type needs one. */
    action?: string;
};

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

    // `action` is not part of the path: it says what the reader is being asked
    // to do, and there is exactly one destination for that here. Named as the
    // tab the proposal actually lives on rather than the meeting root, because
    // "a summary is ready to review" that lands on the meeting's overview has
    // sent the reader one click short of the thing it announced.
    if (
        resource?.type === "board-meeting" &&
        resource.id != null &&
        (resource.action === "review-summary" ||
            resource.action === undefined)
    ) {
        return `/board-meetings/${resource.id}?tab=analysis`;
    }

    return null;
}