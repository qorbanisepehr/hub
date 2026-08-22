import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import {
    useTable,
    stockFeatures,
    type ColumnDef,
    type ColumnVisibilityState,
    type Row,
    type StockFeatures,
} from "@tanstack/react-table";
import { IconClipboardList, IconRefresh, IconChevronRight, IconChevronDown } from "@tabler/icons-react";

import { useAuditLogs, useAuditEvents, useAuditLogDetail } from "@/features/audit/hooks";
import { getAuditLogColumns } from "@/features/audit/audit-logs-columns";
import { DataTablePage, DataTableToolbar } from "@/components/data-table";
import { useTableUrlState } from "@/hooks/use-table-url-state";
import { PERMISSIONS } from "@/lib/permissions";
import { auditKeys } from "@/lib/query-keys";
import { PAGINATION } from "@/lib/constants";
import {
    AUDIT_CATEGORY_LABELS,
    AUDIT_EVENT_LABELS,
} from "@/features/audit/constants";
import type { AuditCategory, AuditLog, AuditLogDetail } from "@/features/audit/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { toPersianDate } from "@/lib/date-format";

const route = getRouteApi("/protected/audit");

function ExpandedRowContent({ log }: { log: AuditLog }) {
    const { data: response, isLoading, isError } = useAuditLogDetail(
        log.id,
    );

    if (isLoading) {
        return (
            <div className="p-4 space-y-3">
                <Skeleton className="h-4 w-48" />
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-16 w-full" />
            </div>
        );
    }

    const detail = response?.data;

    if (!detail) {
        if (isError) {
            return (
                <p className="p-4 text-sm text-muted-foreground">
                    خطا در بارگذاری جزئیات رویداد.
                </p>
            );
        }

        return null;
    }

    const changes = detail.changes ?? {};
    const request = detail.request;

    return (
        <div className="p-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div className="space-y-2">
                <h4 className="font-medium text-muted-foreground">اطلاعات رویداد</h4>
                <div className="space-y-1">
                    <div><span className="text-muted-foreground">نوع: </span>{AUDIT_EVENT_LABELS[detail.event] ?? detail.event}</div>
                    <div><span className="text-muted-foreground">توضیحات: </span>{detail.description ?? "—"}</div>
                    <div><span className="text-muted-foreground">آدرس IP: </span><span className="font-mono text-xs">{detail.ip_address ?? "—"}</span></div>
                </div>
            </div>
            <div className="space-y-2">
                <h4 className="font-medium text-muted-foreground">تغییرات</h4>
                {changes.old && (
                    <div>
                        <span className="text-muted-foreground text-xs">قبل:</span>
                        <pre className="mt-1 p-2 bg-muted rounded text-xs overflow-auto max-h-32">
                            {JSON.stringify(changes.old, null, 2)}
                        </pre>
                    </div>
                )}
                {changes.new && (
                    <div>
                        <span className="text-muted-foreground text-xs">بعد:</span>
                        <pre className="mt-1 p-2 bg-muted rounded text-xs overflow-auto max-h-32">
                            {JSON.stringify(changes.new, null, 2)}
                        </pre>
                    </div>
                )}
                {!changes.old && !changes.new && (
                    <span className="text-muted-foreground">—</span>
                )}
            </div>
            <div className="space-y-2">
                <h4 className="font-medium text-muted-foreground">درخواست</h4>
                {request ? (
                    <div className="space-y-1">
                        {request.method && <div><span className="text-muted-foreground">روش: </span><Badge variant="outline" className="text-xs">{request.method}</Badge></div>}
                        {request.url && <div className="truncate"><span className="text-muted-foreground">آدرس: </span><span className="font-mono text-xs">{request.url}</span></div>}
                        {request.request_id && <div className="truncate"><span className="text-muted-foreground">شناسه درخواست: </span><span className="font-mono text-xs">{request.request_id}</span></div>}
                        {request.trace_id && <div className="truncate"><span className="text-muted-foreground">شناسه ردیابی: </span><span className="font-mono text-xs">{request.trace_id}</span></div>}
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </div>
        </div>
    );
}

export function AuditLogsPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const [columnVisibility, setColumnVisibility] =
        useState<ColumnVisibilityState>({});
    const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({});

    const {
        sorting,
        onSortingChange,
        pagination,
        onPaginationChange,
        globalFilter,
        onGlobalFilterChange,
        columnFilters,
        onColumnFiltersChange,
        ensurePageInRange,
    } = useTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate: navigate as never,
        pagination: {
            defaultPage: 1,
            defaultPageSize: PAGINATION.DEFAULT_PAGE_SIZE,
        },
        sorting: { sortKey: "sort", orderKey: "order" },
        globalFilter: { enabled: true, key: "filter" },
        columnFilters: [
            {
                columnId: "category",
                searchKey: "category",
                type: "string",
            },
            {
                columnId: "event",
                searchKey: "event",
                type: "string",
            },
        ],
    });

    const activeSort = sorting[0];
    const activeCategory = (
        columnFilters.find((f) => f.id === "category")?.value as
            | string[]
            | undefined
    )?.[0] as AuditCategory | undefined;
    const activeEvent = (
        columnFilters.find((f) => f.id === "event")?.value as
            | string[]
            | undefined
    )?.[0] as string | undefined;

    const { data: availableEvents = [] } = useAuditEvents(activeCategory);    const { data, isLoading, isError, isFetching } = useAuditLogs({
        page: pagination.pageIndex + 1,
        per_page: pagination.pageSize,
        sort: activeSort?.id,
        order: activeSort?.desc ? "desc" : "asc",
        search: globalFilter || undefined,
        category: activeCategory,
        event: activeEvent,
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;
    const baseColumns = getAuditLogColumns();
    const columns: ColumnDef<StockFeatures, AuditLog>[] = [
        {
            id: "expand",
            header: "",
            cell: ({ row }: { row: Row<StockFeatures, AuditLog> }) => {
                const isExpanded = expandedRows[row.original.id] ?? false;
                return (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() =>
                            setExpandedRows((prev) => ({
                                ...prev,
                                [row.original.id]: !prev[row.original.id],
                            }))
                        }
                    >
                        {isExpanded ? (
                            <IconChevronDown className="size-4" />
                        ) : (
                            <IconChevronRight className="size-4" />
                        )}
                    </Button>
                );
            },
        },
        ...baseColumns,
    ];

    const table = useTable({
        features: stockFeatures,
        data: tableData,
        columns,
        state: {
            sorting,
            pagination,
            columnVisibility,
            columnFilters,
        },
        onSortingChange,
        onPaginationChange,
        onColumnVisibilityChange: setColumnVisibility,
        onColumnFiltersChange,
        manualPagination: true,
        manualSorting: true,
        pageCount: meta?.last_page ?? 1,
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
            title="لاگ فعالیت"
            totalLabel="رویداد"
            icon={IconClipboardList}
            expandedRowIds={expandedRows}
            renderExpandedRow={(log) => <ExpandedRowContent log={log} />}
            header={
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        لاگ فعالیت
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        مشاهده تمام رویدادهای سیستم
                    </p>
                </div>
            }
            toolbar={
                <div className="flex items-center gap-2">
                    <DataTableToolbar
                        table={table}
                        searchPlaceholder="جستجو در لاگ..."
                        globalFilter={globalFilter}
                        onGlobalFilterChange={onGlobalFilterChange}
                        filters={[
                            {
                                columnId: "category",
                                title: "دسته‌بندی",
                                options: Object.entries(AUDIT_CATEGORY_LABELS).map(
                                    ([value, label]) => ({
                                        label,
                                        value,
                                    }),
                                ),
                            },
                            {
                                columnId: "event",
                                title: "رویداد",
                                options: availableEvents.map((event) => ({
                                    label: AUDIT_EVENT_LABELS[event] ?? event,
                                    value: event,
                                })),
                            },
                        ]}
                    />
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() =>
                            queryClient.invalidateQueries({
                                queryKey: auditKeys.all,
                            })
                        }
                        disabled={isFetching}
                    >
                        <IconRefresh className={`size-4 ${isFetching ? "animate-spin" : ""}`} />
                    </Button>
                </div>
            }
            emptyMessage="هیچ رویدادی ثبت نشده است"
            onRetry={() =>
                queryClient.invalidateQueries({
                    queryKey: auditKeys.all,
                })
            }
            colSpan={columns.length}
        />
    );
}
