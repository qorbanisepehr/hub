import { useCallback } from "react";
import { useNavigate } from "@tanstack/react-router";
import { toast } from "sonner";

import type { AppNotification } from "@/features/notifications/types";

/**
 * Fallback destination when the resource type has no dedicated page yet
 * (architecture §25 — the notification center always exists).
 */
const NOTIFICATION_CENTER_PATH = "/notifications";

/** Resource reference carried by the broadcast payload (architecture §25). */
type NotificationResource = { type?: string; id?: string | number };

/**
 * Deep link for a notification's resource. Only real destinations are
 * mapped; unknown types fall back to the notification center instead of
 * guessing a route (architecture §53).
 */
export function notificationHref(notification: AppNotification): string {
    const resource = notification.data.resource as
        | NotificationResource
        | undefined;

    if (resource?.type === "questionnaire" && resource.id != null) {
        return `/questionnaires/${resource.id}`;
    }

    return NOTIFICATION_CENTER_PATH;
}

/** True when the browser supports OS notifications with granted access. */
function canShowOsNotification(): boolean {
    return (
        typeof Notification !== "undefined" &&
        Notification.permission === "granted"
    );
}

/**
 * True once a permission request was raised in this session, so repeated
 * dismissals do not keep prompting (explicit denials are never re-asked).
 */
let permissionAsked = false;

/**
 * Renders a realtime notification the way the user chose: an OS-level
 * desktop notification while the tab is hidden, an in-app sonner toast
 * otherwise. When the OS path is unavailable or permission is denied,
 * the in-app toast is the graceful fallback (it persists in the DOM, so a
 * returning user still sees it — architecture §29, §30).
 *
 * The first hidden-tab notification asks for permission only if it is still
 * pending; clicking the OS notification opens the resource deep link (or the
 * notification center when the resource type has no dedicated page).
 */
export function useRealtimeNotificationToast() {
    const navigate = useNavigate();

    return useCallback(
        (notification: AppNotification): void => {
            const href = notificationHref(notification);

            const showInAppToast = (): void => {
                toast(notification.title, {
                    description: notification.body ?? undefined,
                });
            };

            const showOsNotification = (): void => {
                if (typeof Notification === "undefined") {
                    return;
                }

                const instance = new Notification(notification.title, {
                    body: notification.body ?? undefined,
                });
                instance.addEventListener("click", (): void => {
                    instance.close();
                    window.focus();
                    void navigate({ to: href });
                });
            };

            if (document.visibilityState !== "hidden") {
                showInAppToast();
                return;
            }

            if (canShowOsNotification()) {
                showOsNotification();
                return;
            }

            if (
                !permissionAsked &&
                typeof Notification !== "undefined" &&
                Notification.permission === "default"
            ) {
                permissionAsked = true;
                void Notification.requestPermission().then((permission) => {
                    if (permission === "granted") {
                        showOsNotification();
                    } else {
                        showInAppToast();
                    }
                });
                return;
            }

            showInAppToast();
        },
        [navigate],
    );
}