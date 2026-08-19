import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import {
    useTable,
    stockFeatures,
    type ColumnVisibilityState,
} from "@tanstack/react-table";
import { IconClipboardList } from "@tabler/icons-react";

import { useAuditLogs } from "@/features/audit/hooks";
import { getAuditLogColumns } from "@/features/audit/audit-logs-columns";
import { DataTablePage, DataTableToolbar } from "@/components/data-table";
import { useTableUrlState } from "@/hooks/use-table-url-state";
import { PERMISSIONS } from "@/lib/permissions";
import { auditKeys } from "@/lib/query-keys";
import { PAGINATION } from "@/lib/constants";
import { AUDIT_CATEGORY_LABELS, AUDIT_PER_PAGE_OPTIONS } from "@/features/audit/constants";
import type { AuditCategory } from "@/features/audit/types";

const route = getRouteApi("/protected/audit");

export function AuditLogsPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const [columnVisibility, setColumnVisibility] =
        useState<ColumnVisibilityState>({});

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
        ],
    });

    const activeSort = sorting[0];
    const activeCategory = (
        columnFilters.find((f) => f.id === "category")?.value as
            | string[]
            | undefined
    )?.[0] as AuditCategory | undefined;

    const { data, isLoading, isError } = useAuditLogs({
        page: pagination.pageIndex + 1,
        per_page: pagination.pageSize,
        sort: activeSort?.id,
        order: activeSort?.desc ? "desc" : "asc",
        search: globalFilter || undefined,
        category: activeCategory,
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;
    const columns = getAuditLogColumns();

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
            perPageOptions={AUDIT_PER_PAGE_OPTIONS}
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
                    ]}
                />
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
