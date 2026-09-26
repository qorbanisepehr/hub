import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import { IconCalendarStats, IconRefresh } from "@tabler/icons-react";

import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Link } from "@tanstack/react-router";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { reportingKeys } from "@/lib/query-keys";
import { attendanceColumns } from "../attendance-columns";
import { useAttendanceReport } from "../hooks";
import type { AttendanceListParams } from "../types";

const route = getRouteApi("/protected/reports/attendance");

const FRESHNESS_LABELS: Record<string, string> = {
    live: "زنده",
    cached: "کش‌شده",
    snapshot: "اسنپ‌شات",
    synchronized: "همگام‌شده",
};

/** Local-time `yyyy-MM-dd` (matches what DatePicker emits). */
function localIso(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

/** Default window: the last 7 days (the API caps the range at 92 days). */
export function defaultFrom(): string {
    const date = new Date();
    date.setDate(date.getDate() - 6);
    return localIso(date);
}

/** Freshness badge beside the title (live = success). */
export function FreshnessBadge({ freshness }: { freshness: string }) {
    return (
        <Badge variant={freshness === "live" ? "success" : "secondary"}>
            <IconCalendarStats className="size-3" />
            {FRESHNESS_LABELS[freshness] ?? freshness}
        </Badge>
    );
}

export function AttendanceReportPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            columnFilters: [
                { columnId: "date_from", searchKey: "date_from", type: "string" },
                { columnId: "date_to", searchKey: "date_to", type: "string" },
            ],
        },
    });

    const { ensurePageInRange } = url;

    // The window is required (historical report): when the URL carries no
    // window yet, default to the last 7 days. The FilterBar's Jalali
    // date pickers write straight into the URL state.
    const dateFrom = url.activeValue("date_from") ?? defaultFrom();
    const dateTo = url.activeValue("date_to") ?? localIso(new Date());

    const params: AttendanceListParams = {
        date_from: dateFrom,
        date_to: dateTo,
        page: url.pagination.pageIndex + 1,
        per_page: url.pagination.pageSize,
        sort: url.activeSort?.id,
        order: url.activeSort?.desc ? "desc" : "asc",
        filter: url.globalFilter || undefined,
    };

    const { data, isLoading, isError, isFetching } = useAttendanceReport(params);

    const meta = data?.meta;

    const table = useDataTable({
        columns: attendanceColumns,
        data: data?.data ?? [],
        meta,
        url,
    });

    // URL may hold a page beyond the last one (e.g. after the window
    // narrows); the shared hook pulls it back into range post-load.
    useEffect(() => {
        if (!isLoading && meta) {
            ensurePageInRange(table.getPageCount());
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [table, ensurePageInRange, isLoading, meta]);

    const refresh = () =>
        queryClient.invalidateQueries({ queryKey: reportingKeys.all });

    const noLinkedEmployee =
        !isLoading &&
        !isError &&
        data !== undefined &&
        data.metadata.reason === "no_linked_employee";

    return (
        <DataTablePage
            table={table}
            meta={meta}
            isLoading={isLoading}
            isError={isError}
            title="حضور و غیاب"
            totalLabel="روز"
            icon={IconCalendarStats}
            header={
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <ListPageHeader
                        title="حضور و غیاب"
                        description="گزارش ورود و خروج شما از سیستم منبع خارجی"
                    />
                    {data && (
                        <FreshnessBadge freshness={String(data.freshness)} />
                    )}
                </div>
            }
            searchPlaceholder="جستجو در تاریخ..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
                { id: "date_from", label: "از تاریخ", type: "date" },
                { id: "date_to", label: "تا تاریخ", type: "date" },
            ]}
            actions={[
                {
                    id: "refresh",
                    label: "تازه‌سازی",
                    icon: IconRefresh,
                    onClick: refresh,
                    disabled: isFetching,
                },
            ]}
            emptyMessage="برای این بازه سابقه‌ای ثبت نشده است"
            emptyAction={
                noLinkedEmployee ? (
                    <Button
                        variant="link"
                        className="mt-2"
                        nativeButton={false}
                        render={<Link to="/profile" />}
                    >
                        به حساب شما پروفایل کارمندی متصل نیست
                    </Button>
                ) : undefined
            }
            onRetry={refresh}
            colSpan={attendanceColumns.length}
        />
    );
}
