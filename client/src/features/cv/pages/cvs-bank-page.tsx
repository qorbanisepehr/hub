import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import {
    useTable,
    stockFeatures,
    type ColumnVisibilityState,
} from "@tanstack/react-table";
import { IconFileCv } from "@tabler/icons-react";

import { DataTablePage, DataTableToolbar, TableFilterBar } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { fetchCvBank } from "@/features/cv/api";
import { cvBankColumns } from "@/features/cv/columns";
import { CV_STATUS_OPTIONS } from "@/features/cv/constants";
import { useTableUrlState } from "@/hooks/use-table-url-state";
import { cvKeys } from "@/lib/query-keys";
import { PAGINATION } from "@/lib/constants";

const route = getRouteApi("/protected/cvs");

export function CvsBankPage() {
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
                columnId: "status",
                searchKey: "status",
                type: "string",
            },
            {
                columnId: "status_not",
                searchKey: "status_not",
                type: "string",
            },
        ],
    });

    const activeSort = sorting[0];
    const activeStatus = (
        columnFilters.find((f) => f.id === "status")?.value as
            | string[]
            | undefined
    )?.[0];
    const activeStatusNot = (
        columnFilters.find((f) => f.id === "status_not")?.value as
            | string[]
            | undefined
    )?.[0];

    const { data, isLoading, isError } = useQuery({
        queryKey: cvKeys.bank({
            page: pagination.pageIndex + 1,
            per_page: pagination.pageSize,
            sort: activeSort?.id,
            order: activeSort?.desc ? "desc" : "asc",
            filter: globalFilter,
            status: activeStatus,
            status_not: activeStatusNot,
        }),
        queryFn: async () => {
            const { data } = await fetchCvBank({
                page: pagination.pageIndex + 1,
                per_page: pagination.pageSize,
                sort: activeSort?.id,
                order: activeSort?.desc ? "desc" : "asc",
                filter: globalFilter || undefined,
                status: activeStatus,
                status_not: activeStatusNot,
            });
            return data;
        },
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

    const table = useTable({
        features: stockFeatures,
        data: tableData,
        columns: cvBankColumns,
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
            title="لیست رزومه‌ها"
            totalLabel="رزومه"
            icon={IconFileCv}
            header={
                <ListPageHeader
                    title="بانک رزومه"
                    description="همه رزومه‌های داوطلبان (قابل فیلتر بر اساس وضعیت)"
                />
            }
            toolbar={
                <DataTableToolbar
                    table={table}
                    searchPlaceholder="جستجوی رزومه..."
                    globalFilter={globalFilter}
                    onGlobalFilterChange={onGlobalFilterChange}
                    filterBar={
                        <TableFilterBar
                            fields={[
                                {
                                    id: "status",
                                    label: "وضعیت",
                                    type: "select",
                                    options: CV_STATUS_OPTIONS,
                                    negatable: true,
                                },
                            ]}
                            columnFilters={columnFilters}
                            onColumnFiltersChange={onColumnFiltersChange}
                        />
                    }
                />
            }
            emptyAction={null}
            onRetry={() =>
                queryClient.invalidateQueries({ queryKey: cvKeys.all })
            }
            colSpan={cvBankColumns.length}
        />
    );
}
