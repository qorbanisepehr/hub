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
import { fetchUsers, fetchAllRoles } from "@/features/rbac/api";
import { getUserColumns } from "@/features/rbac/user-columns";
import { DataTablePage, DataTableToolbar } from "@/components/data-table";
import { useTableUrlState } from "@/hooks/use-table-url-state";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { roleKeys, userKeys } from "@/lib/query-keys";

const route = getRouteApi("/protected/users");

export function UsersPage() {
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
        sorting: { sortKey: "sort", orderKey: "order", defaultSort: "name", defaultOrder: "asc" },
        globalFilter: { enabled: true, key: "filter" },
        columnFilters: [
            {
                columnId: "roles",
                searchKey: "role",
                type: "string",
            },
            {
                columnId: "is_active",
                searchKey: "is_active",
                type: "string",
                serialize: (v) => v === "true" ? true : v === "false" ? false : undefined,
                deserialize: (v) => typeof v === "boolean" ? (v ? "true" : "false") : v,
            },
        ],
    });

    const activeSort = sorting[0];
    const activeRole =
        (columnFilters.find((f) => f.id === "roles")
            ?.value as string[] | undefined)?.[0];
    const activeIsActive =
        (columnFilters.find((f) => f.id === "is_active")
            ?.value as string[] | undefined)?.[0];

    const { data, isLoading, isError } = useQuery({
        queryKey: userKeys.list({
            page: pagination.pageIndex + 1,
            per_page: pagination.pageSize,
            sort: activeSort?.id,
            order: activeSort?.desc ? "desc" : "asc",
            filter: globalFilter,
            role: activeRole,
            is_active: activeIsActive,
        }),
        queryFn: async () => {
            const { data } = await fetchUsers({
                page: pagination.pageIndex + 1,
                per_page: pagination.pageSize,
                sort: activeSort?.id,
                order: activeSort?.desc ? "desc" : "asc",
                filter: globalFilter || undefined,
                role: activeRole || undefined,
                is_active: activeIsActive === "true"
                    ? true
                    : activeIsActive === "false"
                        ? false
                        : undefined,
            });
            return data;
        },
    });

    const { data: rolesData } = useQuery({
        queryKey: roleKeys.filterOptions(),
        queryFn: async () => {
            const { data } = await fetchAllRoles();
            return data.data;
        },
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

    const columns = getUserColumns();

    const table = useReactTable({
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

    const roleFilterOptions =
        rolesData?.map((r) => ({ label: r.display_name, value: String(r.id) })) ?? [];

    return (
        <DataTablePage
            table={table}
            meta={meta}
            isLoading={isLoading}
            isError={isError}
            title="لیست کاربران"
            totalLabel="کاربر"
            icon={IconUsers}
            header={
                <>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            کاربران
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            مدیریت نقش‌های کاربران
                        </p>
                    </div>
                    <PermissionGuard permission="user.create">
                        <Button
                            nativeButton={false}
                            render={<Link to="/users/create" />}
                        >
                            <IconPlus className="size-4" />
                            کاربر جدید
                        </Button>
                    </PermissionGuard>
                </>
            }
            toolbar={
                <DataTableToolbar
                    table={table}
                    searchPlaceholder="جستجوی کاربر..."
                    globalFilter={globalFilter}
                    onGlobalFilterChange={onGlobalFilterChange}
                    filters={[
                        ...(roleFilterOptions.length > 0
                            ? [{
                                  columnId: "roles",
                                  title: "نقش",
                                  options: roleFilterOptions,
                              }]
                            : []),
                        {
                            columnId: "is_active",
                            title: "وضعیت",
                            options: [
                                { label: "فعال", value: "true" },
                                { label: "غیرفعال", value: "false" },
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
                    render={<Link to="/users/create" />}
                >
                    اولین کاربر را ایجاد کنید
                </Button>
            }
            onRetry={() =>
                queryClient.invalidateQueries({
                    queryKey: userKeys.all,
                })
            }
            colSpan={columns.length}
        />
    );
}
