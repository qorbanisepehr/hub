import { useEffect } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, getRouteApi } from "@tanstack/react-router";
import { IconPlus, IconUserCog } from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { deleteRole, fetchRoles, toggleRole } from "@/features/rbac/api";
import { getRoleColumns } from "@/features/rbac/columns";
import { getApiError } from "@/lib/error-utils";
import { PERMISSIONS } from "@/lib/permissions";
import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { roleKeys } from "@/lib/query-keys";

const route = getRouteApi("/protected/roles");

export function RolesPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            sorting: {
                defaultSort: "display_name",
                defaultOrder: "asc",
            },
            columnFilters: [
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

    const activeIsActive = url.activeValue("is_active");
    const { ensurePageInRange } = url;

    const { data, isLoading, isError } = useQuery({
        queryKey: roleKeys.list({
            page: url.pagination.pageIndex + 1,
            per_page: url.pagination.pageSize,
            sort: url.activeSort?.id,
            order: url.activeSort?.desc ? "desc" : "asc",
            filter: url.globalFilter,
            is_active: activeIsActive,
        }),
        queryFn: async () => {
            const { data: response } = await fetchRoles({
                page: url.pagination.pageIndex + 1,
                per_page: url.pagination.pageSize,
                sort: url.activeSort?.id,
                order: url.activeSort?.desc ? "desc" : "asc",
                filter: url.globalFilter || undefined,
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

    const toggleMutation = useMutation({
        mutationFn: (id: number) => toggleRole(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: roleKeys.all });
            toast.success("وضعیت نقش به‌روزرسانی شد");
        },
        onError: (err: unknown) => {
            toast.error(getApiError(err));
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => deleteRole(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: roleKeys.all });
            toast.success("نقش حذف شد");
        },
        onError: (err: unknown) => {
            toast.error(getApiError(err));
        },
    });

    const columns = getRoleColumns({
        onToggle: (role) => toggleMutation.mutate(role.id),
        onDelete: (role) => deleteMutation.mutate(role.id),
        isToggling: toggleMutation.isPending,
        isDeleting: deleteMutation.isPending,
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

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

    return (
        <DataTablePage
            table={table}
            meta={meta}
            isLoading={isLoading}
            isError={isError}
            title="لیست نقش‌ها"
            totalLabel="نقش"
            icon={IconUserCog}
            header={
                <ListPageHeader
                    title="نقش‌ها"
                    description="مدیریت نقش‌ها و سطوح دسترسی"
                    actions={[
                        {
                            label: "نقش جدید",
                            icon: <IconPlus className="size-4" />,
                            href: "/roles/create",
                            permission: PERMISSIONS.ROLE_CREATE,
                        },
                    ]}
                />
            }
            searchPlaceholder="جستجوی نقش..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
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
                    render={<Link to="/roles/create" />}
                >
                    اولین نقش را ایجاد کنید
                </Button>
            }
            onRetry={() =>
                queryClient.invalidateQueries({
                    queryKey: roleKeys.all,
                })
            }
            colSpan={columns.length}
        />
    );
}
