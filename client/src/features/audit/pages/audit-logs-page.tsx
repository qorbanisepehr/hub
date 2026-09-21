import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import type {
    ColumnDef,
    Row,
    StockFeatures,
} from "@tanstack/react-table";
import {
    IconClipboardList,
    IconRefresh,
    IconChevronRight,
    IconChevronDown,
    IconDownload,
} from "@tabler/icons-react";

import { useAuditLogs, useAuditEvents, useAuditLogDetail } from "@/features/audit/hooks";
import { exportAuditLogs } from "@/features/audit/api";
import { getAuditLogColumns } from "@/features/audit/audit-logs-columns";
import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { getApiError } from "@/lib/error-utils";
import { saveBlobResponse, exportDateStamp } from "@/lib/download";
import { toast } from "sonner";
import { auditKeys } from "@/lib/query-keys";
import {
    AUDIT_CATEGORY_LABELS,
    AUDIT_EVENT_LABELS,
} from "@/features/audit/constants";
import type { AuditCategory, AuditLog } from "@/features/audit/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { AuditDiffView } from "@/features/audit/components/audit-diff-view";

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
                <AuditDiffView old={changes.old} new={changes.new} />
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

    const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({});
    const [isExporting, setIsExporting] = useState(false);

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            columnFilters: [
                {
                    columnId: "category",
                    searchKey: "category",
                    type: "string",
                },
                {
                    columnId: "category_not",
                    searchKey: "category_not",
                    type: "string",
                },
                {
                    columnId: "event",
                    searchKey: "event",
                    type: "string",
                },
                {
                    columnId: "event_not",
                    searchKey: "event_not",
                    type: "string",
                },
                {
                    columnId: "date_from",
                    searchKey: "date_from",
                    type: "string",
                },
                {
                    columnId: "date_to",
                    searchKey: "date_to",
                    type: "string",
                },
            ],
        },
    });

    const activeCategory = url.activeValue("category") as AuditCategory | undefined;
    const activeCategoryNot = url.activeValue("category_not");
    const activeEvent = url.activeValue("event");
    const activeEventNot = url.activeValue("event_not");
    const activeDateFrom = url.activeValue("date_from");
    const activeDateTo = url.activeValue("date_to");
    const { ensurePageInRange } = url;

    const { data: availableEvents = [] } = useAuditEvents(activeCategory);
    const { data, isLoading, isError, isFetching } = useAuditLogs({
        page: url.pagination.pageIndex + 1,
        per_page: url.pagination.pageSize,
        sort: url.activeSort?.id,
        order: url.activeSort ? (url.activeSort.desc ? "desc" : "asc") : undefined,
        filter: url.globalFilter || undefined,
        category: activeCategory,
        category_not: activeCategoryNot || undefined,
        event: activeEvent,
        event_not: activeEventNot || undefined,
        date_from: activeDateFrom || undefined,
        date_to: activeDateTo || undefined,
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
                            <IconChevronRight className="size-4 rtl:-scale-x-100" />
                        )}
                    </Button>
                );
            },
        },
        ...baseColumns,
    ];

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

    const handleExport = async () => {
        setIsExporting(true);

        try {
            const response = await exportAuditLogs({
                format: "csv",
                filter: url.globalFilter || undefined,
                category: activeCategory,
                category_not: activeCategoryNot || undefined,
                event: activeEvent,
                event_not: activeEventNot || undefined,
                date_from: activeDateFrom || undefined,
                date_to: activeDateTo || undefined,
            });

            saveBlobResponse(
                response,
                `audit-logs-${exportDateStamp()}.csv`,
                "text/csv;charset=utf-8",
            );
        } catch (err) {
            toast.error(getApiError(err) ?? "خطا در دریافت فایل خروجی");
        } finally {
            setIsExporting(false);
        }
    };

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
            getExpandedRowId={(log) => String(log.id)}
            renderExpandedRow={(log) => <ExpandedRowContent log={log} />}
            header={
                <ListPageHeader
                    title="لاگ فعالیت"
                    description="مشاهده تمام رویدادهای سیستم"
                />
            }
            searchPlaceholder="جستجو در لاگ..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
                {
                    id: "category",
                    label: "دسته‌بندی",
                    type: "select",
                    options: Object.entries(
                        AUDIT_CATEGORY_LABELS,
                    ).map(([value, label]) => ({
                        label,
                        value,
                    })),
                    negatable: true,
                },
                {
                    id: "event",
                    label: "رویداد",
                    type: "select",
                    options: availableEvents.map(
                        (event) => ({
                            label:
                                AUDIT_EVENT_LABELS[event] ??
                                event,
                            value: event,
                        }),
                    ),
                    negatable: true,
                },
                {
                    id: "date_from",
                    label: "از تاریخ",
                    type: "date",
                },
                {
                    id: "date_to",
                    label: "تا تاریخ",
                    type: "date",
                },
            ]}
            actions={[
                {
                    id: "export",
                    label: "خروجی CSV",
                    icon: IconDownload,
                    onClick: handleExport,
                    disabled: isExporting,
                },
                {
                    id: "refresh",
                    label: "تازهسازی",
                    icon: IconRefresh,
                    onClick: () =>
                        queryClient.invalidateQueries({
                            queryKey: auditKeys.all,
                        }),
                    disabled: isFetching,
                },
            ]}
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
