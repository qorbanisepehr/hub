import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import {
    getCoreRowModel,
    useReactTable,
    type VisibilityState,
} from "@tanstack/react-table";
import { IconFileCv } from "@tabler/icons-react";

import { DataTablePage, DataTableToolbar } from "@/components/data-table";
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

    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
        {},
    );

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
        ],
    });

    const activeSort = sorting[0];
    const activeStatus =
        (columnFilters.find((f) => f.id === "status")?.value as string[] | undefined)?.[0];

    const { data, isLoading, isError } = useQuery({
        queryKey: cvKeys.bank({
            page: pagination.pageIndex + 1,
            per_page: pagination.pageSize,
            sort: activeSort?.id,
            order: activeSort?.desc ? "desc" : "asc",
            filter: globalFilter,
            status: activeStatus,
        }),
        queryFn: async () => {
            const { data } = await fetchCvBank({
                page: pagination.pageIndex + 1,
                per_page: pagination.pageSize,
                sort: activeSort?.id,
                order: activeSort?.desc ? "desc" : "asc",
                filter: globalFilter || undefined,
                status: activeStatus,
            });
            return data;
        },
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

    const table = useReactTable({
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
        getCoreRowModel: getCoreRowModel(),
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
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        بانک رزومه
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        همه رزومه‌های داوطلبان (قابل فیلتر بر اساس وضعیت)
                    </p>
                </div>
            }
            toolbar={
                <DataTableToolbar
                    table={table}
                    searchPlaceholder="جستجوی رزومه..."
                    globalFilter={globalFilter}
                    onGlobalFilterChange={onGlobalFilterChange}
                    filters={[
                        {
                            columnId: "status",
                            title: "وضعیت",
                            options: CV_STATUS_OPTIONS,
                        },
                    ]}
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
