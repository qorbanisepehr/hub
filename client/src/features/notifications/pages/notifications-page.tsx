import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import { IconBell, IconChecklist, IconRefresh } from "@tabler/icons-react";

import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { getNotificationColumns } from "@/features/notifications/columns";
import { NOTIFICATION_STATUS_OPTIONS } from "@/features/notifications/constants";
import {
    useMarkAllNotificationsRead,
    useMarkNotificationRead,
    useNotifications,
} from "@/features/notifications/hooks";
import { useDataTable, useDataTableUrlState } from "@/hooks/use-data-table-page";
import { notificationKeys } from "@/lib/query-keys";

const route = getRouteApi("/protected/notifications");

export function NotificationsPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            columnFilters: [
                // Boolean-table filter: `unread=1` / `unread=0` (no negation).
                { columnId: "unread", searchKey: "unread", type: "string" },
            ],
        },
    });

    const { ensurePageInRange } = url;
    const activeUnread = url.activeValue("unread");

    const { data, isLoading, isError, isFetching } = useNotifications({
        page: url.pagination.pageIndex + 1,
        per_page: url.pagination.pageSize,
        unread:
            activeUnread === "1"
                ? true
                : activeUnread === "0"
                  ? false
                  : undefined,
    });

    const markRead = useMarkNotificationRead();
    const markAll = useMarkAllNotificationsRead();

    const tableData = data?.data ?? [];
    const meta = data?.meta;

    const columns = getNotificationColumns({
        onMarkRead: (id) => markRead.mutate(id),
        isMarkingRead: (id) => markRead.isPending && markRead.variables === id,
    });

    const table = useDataTable({
        columns,
        data: tableData,
        meta,
        url,
    });

    useEffect(() => {
        if (!isLoading && meta) {
            ensurePageInRange(table.getPageCount());
        }
    }, [table, ensurePageInRange, isLoading, meta]);

    return (
        <DataTablePage
            table={table}
            meta={meta}
            isLoading={isLoading}
            isError={isError}
            title="اعلان‌ها"
            totalLabel="اعلان"
            icon={IconBell}
            header={
                <ListPageHeader
                    title="اعلان‌ها"
                    description="رویدادهای مربوط به شما"
                />
            }
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
                {
                    id: "unread",
                    label: "وضعیت خواندن",
                    type: "select",
                    options: NOTIFICATION_STATUS_OPTIONS,
                },
            ]}
            actions={[
                {
                    id: "read-all",
                    label: "خواندن همه",
                    icon: IconChecklist,
                    onClick: () => markAll.mutate(),
                    disabled: markAll.isPending || !(meta && meta.total > 0),
                },
                {
                    id: "refresh",
                    label: "بازخوانی",
                    icon: IconRefresh,
                    onClick: () =>
                        queryClient.invalidateQueries({
                            queryKey: notificationKeys.all,
                        }),
                    disabled: isFetching,
                },
            ]}
            emptyMessage="اعلانی برای نمایش وجود ندارد"
            onRetry={() =>
                queryClient.invalidateQueries({
                    queryKey: notificationKeys.all,
                })
            }
            colSpan={columns.length}
        />
    );
}