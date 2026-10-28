import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { IconCheck, IconDownload, IconLoader2 } from "@tabler/icons-react";

import { DataTableColumnHeader } from "@/components/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { toPersianDate } from "@/lib/date-format";
import { cn } from "@/lib/utils";
import {
    notificationBundleToken,
    notificationHref,
} from "./notification-href";
import type { AppNotification } from "./types";

type NotificationColumnsOptions = {
    onMarkRead: (id: string) => void;
    isMarkingRead: (id: string) => boolean;
    /** Open a notification with a real destination (navigate + mark read). */
    onOpenNotification?: (notification: AppNotification) => void;
    /** Fetch a finished export bundle by its token. */
    onDownloadBundle?: (token: string) => void;
    isDownloadingBundle?: (token: string) => boolean;
};

/**
 * Notifications are served newest-first by the API (no server sort), so every
 * column is explicitly non-sortable. The read-status *filter* lives on the
 * URL-driven `unread` param — deliberately NOT a table column id — so
 * TanStack never tries to locally filter rows for it (architecture §117).
 */
export function getNotificationColumns({
    onMarkRead,
    isMarkingRead,
    onOpenNotification,
    onDownloadBundle,
    isDownloadingBundle,
}: NotificationColumnsOptions): ColumnDef<StockFeatures, AppNotification>[] {
    return [
        {
            id: "status",
            accessorFn: (row) => (row.read_at === null ? "unread" : "read"),
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="وضعیت" />
            ),
            cell: ({ row }) => {
                const unread = row.original.read_at === null;
                return (
                    <Badge variant={unread ? "warning" : "outline"}>
                        {unread ? "خوانده‌نشده" : "خوانده‌شده"}
                    </Badge>
                );
            },
            meta: { displayName: "وضعیت" },
            enableSorting: false,
        },
        {
            id: "title",
            accessorKey: "title",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="اعلان" />
            ),
            cell: ({ row }) => {
                const { title, body, read_at } = row.original;
                const href = notificationHref(row.original);
                // Only notifications with a real destination become links,
                // and only when the page wires an open handler.
                const isLink = href !== null && onOpenNotification !== undefined;

                return (
                    <div className="min-w-0">
                        {isLink ? (
                            <button
                                type="button"
                                onClick={() =>
                                    onOpenNotification?.(row.original)
                                }
                                className={cn(
                                    "cursor-pointer text-start text-sm leading-snug hover:underline hover:underline-offset-4",
                                    read_at === null && "font-semibold",
                                )}
                            >
                                {title}
                            </button>
                        ) : (
                            <p
                                className={cn(
                                    "text-sm leading-snug",
                                    read_at === null && "font-semibold",
                                )}
                            >
                                {title}
                            </p>
                        )}
                        {body ? (
                            <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                {body}
                            </p>
                        ) : null}
                    </div>
                );
            },
            meta: { displayName: "اعلان" },
            enableSorting: false,
        },
        {
            accessorKey: "created_at",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="تاریخ" />
            ),
            cell: ({ row }) => {
                const createdAt = row.getValue("created_at") as string | null;
                return (
                    <span className="text-sm whitespace-nowrap text-muted-foreground">
                        {createdAt ? toPersianDate(createdAt) : "—"}
                    </span>
                );
            },
            meta: { displayName: "تاریخ" },
            enableSorting: false,
        },
        {
            id: "actions",
            header: "عملیات",
            cell: ({ row }) => {
                const notification = row.original;
                const token = notificationBundleToken(notification);

                // A download outlives the read state: the archive is on disk for
                // a day whether or not the notification was opened, and someone
                // who read it yesterday may still want it today. So the button
                // shows for a read row too, where the mark-read button does not.
                if (token !== null && onDownloadBundle !== undefined) {
                    return (
                        <div className="flex items-center justify-end">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => onDownloadBundle(token)}
                                disabled={isDownloadingBundle?.(token) ?? false}
                            >
                                {isDownloadingBundle?.(token) ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : (
                                    <IconDownload className="size-4" />
                                )}
                                دانلود بسته
                            </Button>
                        </div>
                    );
                }

                if (notification.read_at !== null) {
                    return null;
                }

                return (
                    <div className="flex items-center justify-end">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => onMarkRead(notification.id)}
                            disabled={isMarkingRead(notification.id)}
                            aria-label="علامت‌گذاری به‌عنوان خوانده‌شده"
                        >
                            <IconCheck className="size-4" />
                        </Button>
                    </div>
                );
            },
            enableSorting: false,
            enableHiding: false,
        },
    ];
}