import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, getRouteApi } from "@tanstack/react-router";
import {
    getCoreRowModel,
    useReactTable,
    type VisibilityState,
} from "@tanstack/react-table";
import { IconPlus, IconUserCog } from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { deleteRole, fetchRoles, toggleRole } from "@/features/rbac/api";
import { getRoleColumns } from "@/features/rbac/columns";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { DataTablePage, DataTableToolbar } from "@/components/data-table";
import { useTableUrlState } from "@/hooks/use-table-url-state";

const route = getRouteApi("/protected/roles");

export function RolesPage() {
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
        sorting: { sortKey: "sort", orderKey: "order", defaultSort: "display_name", defaultOrder: "asc" },
        globalFilter: { enabled: true, key: "filter" },
        columnFilters: [
            {
                columnId: "is_active",
                searchKey: "is_active",
                type: "string",
                deserialize: (v) => (typeof v === "boolean" ? (v ? "true" : "false") : v),
                serialize: (v) => (v === "true" ? true : v === "false" ? false : undefined),
            },
        ],
    });

    const activeSort = sorting[0];
    const activeIsActive =
        columnFilters.find((f) => f.id === "is_active")
            ?.value as string ?? undefined;

    const { data, isLoading, isError } = useQuery({
        queryKey: [
            "roles",
            pagination.pageIndex + 1,
            pagination.pageSize,
            activeSort?.id,
            activeSort?.desc ? "desc" : "asc",
            globalFilter,
            activeIsActive,
        ],
        queryFn: async () => {
            const { data } = await fetchRoles({
                page: pagination.pageIndex + 1,
                per_page: pagination.pageSize,
                sort: activeSort?.id,
                order: activeSort?.desc ? "desc" : "asc",
                filter: globalFilter || undefined,
                is_active: activeIsActive === "true"
                    ? true
                    : activeIsActive === "false"
                        ? false
                        : undefined,
            });
            return data;
        },
    });

    const toggleMutation = useMutation({
        mutationFn: (id: number) => toggleRole(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["roles"] });
            toast.success("وضعیت نقش به‌روزرسانی شد");
        },
        onError: () => {
            toast.error("خطا در به‌روزرسانی وضعیت نقش");
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => deleteRole(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["roles"] });
            toast.success("نقش حذف شد");
        },
        onError: () => {
            toast.error("خطا در حذف نقش");
        },
    });

    const columns = getRoleColumns({
        onToggle: (role) => toggleMutation.mutate(role.id),
        onDelete: (role) => {
            if (
                confirm(`آیا از حذف نقش "${role.display_name}" اطمینان دارید؟`)
            ) {
                deleteMutation.mutate(role.id);
            }
        },
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

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
                <>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            نقش‌ها
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            مدیریت نقش‌ها و سطوح دسترسی
                        </p>
                    </div>
                    <PermissionGuard permission="role.create">
                        <Button
                            nativeButton={false}
                            render={<Link to="/roles/create" />}
                        >
                            <IconPlus className="size-4" />
                            نقش جدید
                        </Button>
                    </PermissionGuard>
                </>
            }
            toolbar={
                <DataTableToolbar
                    table={table}
                    searchPlaceholder="جستجوی نقش..."
                    globalFilter={globalFilter}
                    onGlobalFilterChange={onGlobalFilterChange}
                    filters={[
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
                    render={<Link to="/roles/create" />}
                >
                    اولین نقش را ایجاد کنید
                </Button>
            }
            errorAction={
                <Button
                    variant="link"
                    onClick={() =>
                        queryClient.invalidateQueries({
                            queryKey: ["roles"],
                        })
                    }
                >
                    تلاش مجدد
                </Button>
            }
            colSpan={columns.length}
        />
    );
}
