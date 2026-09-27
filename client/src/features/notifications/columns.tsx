import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { IconCheck } from "@tabler/icons-react";

import { DataTableColumnHeader } from "@/components/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { toPersianDate } from "@/lib/date-format";
import { cn } from "@/lib/utils";
import type { AppNotification } from "./types";

type NotificationColumnsOptions = {
    onMarkRead: (id: string) => void;
    isMarkingRead: (id: string) => boolean;
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
                return (
                    <div className="min-w-0">
                        <p
                            className={cn(
                                "text-sm leading-snug",
                                read_at === null && "font-semibold",
                            )}
                        >
                            {title}
                        </p>
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