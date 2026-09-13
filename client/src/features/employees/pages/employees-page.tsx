import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, getRouteApi } from "@tanstack/react-router";
import {
    useTable,
    stockFeatures,
    type ColumnVisibilityState,
} from "@tanstack/react-table";
import { IconPlus, IconUsers } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { fetchEmployees } from "@/features/employees/api";
import { employeeColumns } from "@/features/employees/columns";
import { DataTablePage, DataTableToolbar, TableFilterBar } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { useTableUrlState } from "@/hooks/use-table-url-state";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { employeeKeys } from "@/lib/query-keys";
import { PAGINATION } from "@/lib/constants";

const route = getRouteApi("/protected/employees");

export function EmployeesPage() {
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
                columnId: "employment_status",
                searchKey: "status",
                type: "string",
            },
            {
                columnId: "employment_status_not",
                searchKey: "status_not",
                type: "string",
            },
        ],
    });

    const activeSort = sorting[0];
    const activeStatus = (
        columnFilters.find((f) => f.id === "employment_status")?.value as
            | string[]
            | undefined
    )?.[0];
    const activeStatusNot = (
        columnFilters.find((f) => f.id === "employment_status_not")?.value as
            | string[]
            | undefined
    )?.[0];

    const statusFilterOptions = [
        { label: "فعال", value: "active" },
        { label: "غیرفعال", value: "inactive" },
        { label: "تعلیق", value: "suspended" },
    ];

    const { data, isLoading, isError } = useQuery({
        queryKey: employeeKeys.list({
            page: pagination.pageIndex + 1,
            per_page: pagination.pageSize,
            sort: activeSort?.id,
            order: activeSort?.desc ? "desc" : "asc",
            filter: globalFilter,
            status: activeStatus,
            status_not: activeStatusNot,
        }),
        queryFn: async () => {
            const { data: response } = await fetchEmployees({
                page: pagination.pageIndex + 1,
                per_page: pagination.pageSize,
                sort: activeSort?.id,
                order: activeSort?.desc ? "desc" : "asc",
                filter: globalFilter || undefined,
                status: activeStatus,
                status_not: activeStatusNot,
            });
            return response;
        },
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

    const table = useTable({
        features: stockFeatures,
        data: tableData,
        columns: employeeColumns,
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
        // getCoreRowModel: getCoreRowModel(),
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
            title="لیست کارمندان"
            totalLabel="کارمند"
            icon={IconUsers}
            header={
                <ListPageHeader
                    title="کارمندان"
                    description="مدیریت اطلاعات کارمندان شرکت"
                    action={
                        <PermissionGuard permission={PERMISSIONS.EMPLOYEE_CREATE}>
                            <Button
                                nativeButton={false}
                                render={<Link to="/employees/create" />}
                            >
                                <IconPlus className="size-4" />
                                کارمند جدید
                            </Button>
                        </PermissionGuard>
                    }
                />
            }
            toolbar={
                <DataTableToolbar
                    table={table}
                    searchPlaceholder="جستجوی کارمند..."
                    globalFilter={globalFilter}
                    onGlobalFilterChange={onGlobalFilterChange}
                    filterBar={
                        <TableFilterBar
                            fields={[
                                {
                                    id: "employment_status",
                                    label: "وضعیت اشتغال",
                                    type: "select",
                                    options: statusFilterOptions,
                                    negatable: true,
                                },
                            ]}
                            columnFilters={columnFilters}
                            onColumnFiltersChange={onColumnFiltersChange}
                        />
                    }
                />
            }
            emptyAction={
                <Button
                    variant="link"
                    className="mt-2"
                    nativeButton={false}
                    render={<Link to="/employees/create" />}
                >
                    اولین کارمند را ثبت کنید
                </Button>
            }
            onRetry={() =>
                queryClient.invalidateQueries({
                    queryKey: employeeKeys.all,
                })
            }
            colSpan={employeeColumns.length}
        />
    );
}
