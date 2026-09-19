import { useEffect, useMemo } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, getRouteApi } from "@tanstack/react-router";
import { IconPlus, IconUsers } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { fetchUsers, fetchRoleOptions } from "@/features/rbac/api";
import { getUserColumns } from "@/features/rbac/user-columns";
import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { roleKeys, userKeys } from "@/lib/query-keys";

const route = getRouteApi("/protected/users");

export function UsersPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            sorting: {
                defaultSort: "name",
                defaultOrder: "asc",
            },
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
                    serialize: (v) =>
                        v === "true" ? true : v === "false" ? false : undefined,
                    deserialize: (v) =>
                        typeof v === "boolean" ? (v ? "true" : "false") : v,
                },
            ],
        },
    });

    const activeRole = url.activeValue("roles");
    const activeIsActive = url.activeValue("is_active");
    const { ensurePageInRange } = url;

    const { data, isLoading, isError } = useQuery({
        queryKey: userKeys.list({
            page: url.pagination.pageIndex + 1,
            per_page: url.pagination.pageSize,
            sort: url.activeSort?.id,
            order: url.activeSort?.desc ? "desc" : "asc",
            filter: url.globalFilter,
            role: activeRole,
            is_active: activeIsActive,
        }),
        queryFn: async () => {
            const { data: response } = await fetchUsers({
                page: url.pagination.pageIndex + 1,
                per_page: url.pagination.pageSize,
                sort: url.activeSort?.id,
                order: url.activeSort?.desc ? "desc" : "asc",
                filter: url.globalFilter || undefined,
                role: activeRole || undefined,
                is_active:
                    activeIsActive === "true"
                        ? true
                        : activeIsActive === "false"
                          ? false
                          : undefined,
            });
            return response;
        },
    });

    const { data: rolesData } = useQuery({
        queryKey: roleKeys.filterOptions(),
        queryFn: async () => {
            const { data: response } = await fetchRoleOptions();
            return response.data;
        },
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

    const columns = getUserColumns();

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

    const roleFilterOptions = useMemo(
        () =>
            rolesData?.map((r) => ({
                label: r.label,
                value: String(r.id),
            })) ?? [],
        [rolesData],
    );

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
                <ListPageHeader
                    title="کاربران"
                    description="مدیریت نقش‌های کاربران"
                    action={
                        <PermissionGuard permission={PERMISSIONS.USER_CREATE}>
                            <Button
                                nativeButton={false}
                                render={<Link to="/users/create" />}
                            >
                                <IconPlus className="size-4" />
                                کاربر جدید
                            </Button>
                        </PermissionGuard>
                    }
                />
            }
            searchPlaceholder="جستجوی کاربر..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
                ...(roleFilterOptions.length > 0
                    ? [
                          {
                              id: "roles",
                              label: "نقش",
                              type: "select" as const,
                              options: roleFilterOptions,
                          },
                      ]
                    : []),
                {
                    id: "is_active",
                    label: "وضعیت",
                    type: "select",
                    options: [
                        { label: "فعال", value: "true" },
                        { label: "غیرفعال", value: "false" },
                    ],
                },
            ]}
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
