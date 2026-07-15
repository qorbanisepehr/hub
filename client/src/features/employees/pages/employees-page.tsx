import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, getRouteApi } from "@tanstack/react-router";
import {
    getCoreRowModel,
    useReactTable,
    type VisibilityState,
} from "@tanstack/react-table";
import { IconPlus, IconUsers } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { fetchEmployees } from "@/features/employees/api";
import { employeeColumns } from "@/features/employees/columns";
import { DataTablePage, DataTableToolbar } from "@/components/data-table";
import { useTableUrlState } from "@/hooks/use-table-url-state";
import { PermissionGuard } from "@/features/auth/components/permission-guard";

const route = getRouteApi("/protected/employees");

export function EmployeesPage() {
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
        pagination: { defaultPage: 1, defaultPageSize: 10 },
        sorting: { sortKey: "sort", orderKey: "order" },
        globalFilter: { enabled: true, key: "filter" },
        columnFilters: [
            {
                columnId: "employment_status",
                searchKey: "status",
                type: "string",
            },
        ],
    });

    const activeSort = sorting[0];
    const activeStatus =
        (columnFilters.find((f) => f.id === "employment_status")
            ?.value as string[] | undefined)?.[0];

    const { data, isLoading, isError } = useQuery({
        queryKey: [
            "employees",
            pagination.pageIndex + 1,
            pagination.pageSize,
            activeSort?.id,
            activeSort?.desc ? "desc" : "asc",
            globalFilter,
            activeStatus,
        ],
        queryFn: async () => {
            const { data } = await fetchEmployees({
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
            title="لیست کارمندان"
            totalLabel="کارمند"
            icon={IconUsers}
            header={
                <>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            کارمندان
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            مدیریت اطلاعات کارمندان شرکت
                        </p>
                    </div>
                    <PermissionGuard permission="employee.create">
                        <Button
                            nativeButton={false}
                            render={<Link to="/employees/create" />}
                        >
                            <IconPlus className="size-4" />
                            کارمند جدید
                        </Button>
                    </PermissionGuard>
                </>
            }
            toolbar={
                <DataTableToolbar
                    table={table}
                    searchPlaceholder="جستجوی کارمند..."
                    globalFilter={globalFilter}
                    onGlobalFilterChange={onGlobalFilterChange}
                    filters={[
                        {
                            columnId: "employment_status",
                            title: "وضعیت اشتغال",
                            options: [
                                { label: "فعال", value: "active" },
                                { label: "غیرفعال", value: "inactive" },
                                { label: "تعلیق", value: "suspended" },
                            ],
                        },
                    ]}
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
                    queryKey: ["employees"],
                })
            }
            colSpan={employeeColumns.length}
        />
    );
}
