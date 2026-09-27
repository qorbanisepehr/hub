import { useEffect, useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { IconBell, IconBellOff, IconChecklist } from "@tabler/icons-react";

import { EmptyState } from "@/components/layout";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Skeleton } from "@/components/ui/skeleton";
import {
    useMarkAllNotificationsRead,
    useMarkNotificationRead,
    useNotificationChannel,
    useNotifications,
    useUnreadCount,
} from "@/features/notifications/hooks";
import { toPersianDate } from "@/lib/date-format";
import { cn } from "@/lib/utils";

/** Recent notifications shown in the dropdown panel. */
const RECENT_PAGE_SIZE = 6;

/** Row placeholders rendered while the recent list is loading. */
const SKELETON_ROWS = [0, 1, 2] as const;

/**
 * Header bell: backend-authoritative unread badge + a dropdown of the most
 * recent notifications. Opening the panel reconciles list and count with the
 * API (architecture §54, §56); marking items read is idempotent by
 * notification id (§55). Realtime (Reverb) invalidates the queries on
 * `notification.created` when configured; otherwise the 60s poll covers it
 * (§29/§30).
 */
export function NotificationsBell() {
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);

    useNotificationChannel();

    const unreadCount = useUnreadCount();
    const count = unreadCount.data ?? 0;

    const recent = useNotifications({ page: 1, per_page: RECENT_PAGE_SIZE });
    const markRead = useMarkNotificationRead();
    const markAll = useMarkAllNotificationsRead();

    useEffect(() => {
        if (!open) return;
        void recent.refetch();
        void unreadCount.refetch();
    }, [open, recent, unreadCount]);

    const notifications = recent.data?.data ?? [];

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="ghost"
                        size="icon"
                        className="relative"
                        aria-label={
                            count > 0
                                ? `اعلان‌ها (${count.toLocaleString("fa-IR")} خوانده‌نشده)`
                                : "اعلان‌ها"
                        }
                    />
                }
            >
                <IconBell className="size-5" />
                {count > 0 && (
                    <span className="pointer-events-none absolute -top-1 -end-1 grid size-4 place-items-center rounded-full bg-destructive text-[10px] leading-none text-white">
                        {count > 99 ? "۹۹+" : count.toLocaleString("fa-IR")}
                    </span>
                )}
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-80 p-0 sm:w-96"
            >
                <DropdownMenuLabel className="flex items-center justify-between px-3 py-2.5">
                    <span className="text-sm font-medium text-foreground">
                        اعلان‌ها
                    </span>
                    {count > 0 && (
                        <button
                            type="button"
                            onClick={() => markAll.mutate()}
                            disabled={markAll.isPending}
                            className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-primary transition-colors hover:bg-accent disabled:opacity-50"
                        >
                            <IconChecklist className="size-3.5" />
                            خواندن همه
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="mx-0" />

                <div className="max-h-96 overflow-y-auto">
                    {recent.isLoading ? (
                        <div className="space-y-2 p-3">
                            {SKELETON_ROWS.map((row) => (
                                <Skeleton
                                    key={row}
                                    className="h-12 w-full"
                                />
                            ))}
                        </div>
                    ) : notifications.length > 0 ? (
                        notifications.map((notification) => {
                            const unread = notification.read_at === null;
                            return (
                                <DropdownMenuItem
                                    key={notification.id}
                                    className="items-start gap-2 px-3 py-2"
                                    onClick={() =>
                                        markRead.mutate(notification.id)
                                    }
                                >
                                    <span
                                        className={cn(
                                            "mt-1.5 size-2 shrink-0 rounded-full",
                                            unread
                                                ? "bg-primary"
                                                : "bg-transparent",
                                        )}
                                        aria-hidden="true"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={cn(
                                                "block text-sm leading-snug",
                                                unread && "font-semibold",
                                            )}
                                        >
                                            {notification.title}
                                        </span>
                                        {notification.body ? (
                                            <span className="mt-0.5 block line-clamp-2 text-xs text-muted-foreground">
                                                {notification.body}
                                            </span>
                                        ) : null}
                                        <span className="mt-1 block text-[11px] text-muted-foreground">
                                            {toPersianDate(
                                                notification.created_at,
                                            )}
                                        </span>
                                    </span>
                                </DropdownMenuItem>
                            );
                        })
                    ) : (
                        <EmptyState
                            icon={IconBellOff}
                            message="اعلانی ندارید"
                            variant="compact"
                            className="py-6"
                        />
                    )}
                </div>

                <DropdownMenuSeparator className="mx-0" />
                <DropdownMenuItem
                    className="justify-center font-medium text-primary"
                    onClick={() => {
                        setOpen(false);
                        void navigate({ to: "/notifications" });
                    }}
                >
                    مشاهده همه اعلان‌ها
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}