import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, getRouteApi } from "@tanstack/react-router";
import {
    useTable,
    stockFeatures,
    type ColumnVisibilityState,
} from "@tanstack/react-table";
import { IconDownload, IconPlus, IconUsers } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ExportDialog } from "@/components/shared";
import {
    exportEmployees,
    fetchEmployeeExportFields,
    fetchEmployeeExportTemplate,
    fetchEmployees,
} from "@/features/employees/api";
import { employeeColumns } from "@/features/employees/columns";
import { DataTablePage, DataTableToolbar, TableFilterBar } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { useTableUrlState } from "@/hooks/use-table-url-state";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { employeeKeys } from "@/lib/query-keys";
import { PAGINATION } from "@/lib/constants";
import { saveBlobResponse, exportDateStamp } from "@/lib/download";

const route = getRouteApi("/protected/employees");

async function downloadEmployeeExportTemplate(format: "xlsx" | "csv") {
    const response = await fetchEmployeeExportTemplate(format);

    saveBlobResponse(
        response,
        `employees-template-${exportDateStamp()}.${format}`,
        format === "csv"
            ? "text/csv;charset=utf-8"
            : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    );
}

export function EmployeesPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const [columnVisibility, setColumnVisibility] =
        useState<ColumnVisibilityState>({});
    const [isExportOpen, setIsExportOpen] = useState(false);

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

    const { data: exportFields, isLoading: exportFieldsLoading } = useQuery({
        queryKey: ["employee-export-fields"],
        queryFn: async () => {
            const { data } = await fetchEmployeeExportFields();
            return data.data;
        },
        enabled: isExportOpen,
        staleTime: 5 * 60 * 1000,
    });

    const activeStatusLabel =
        statusFilterOptions.find((o) => o.value === activeStatus)?.label;
    const activeStatusNotLabel = statusFilterOptions.find(
        (o) => o.value === activeStatusNot,
    )?.label;
    const exportFilterSummary = [
        globalFilter ? `جستجو: ${globalFilter}` : null,
        activeStatusLabel ? `وضعیت: ${activeStatusLabel}` : null,
        activeStatusNotLabel ? `به‌جز وضعیت: ${activeStatusNotLabel}` : null,
    ].filter((f): f is string => f !== null);

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
        enableColumnPinning: true,
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

    const handleExport = async ({
        fields,
        format,
        presentation,
    }: {
        fields: string[];
        format: "xlsx" | "csv";
        presentation: import("@/features/employees/api").EmployeeExportPresentation;
    }) => {
        const response = await exportEmployees({
            fields,
            format,
            status: activeStatus,
            status_not: activeStatusNot || undefined,
            presentation,
        });

        saveBlobResponse(
            response,
            `employees-${exportDateStamp()}.${format}`,
            format === "csv"
                ? "text/csv;charset=utf-8"
                : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        );
    };

    return (
        <>
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
                            <div className="flex items-center gap-2">
                                <PermissionGuard
                                    permission={PERMISSIONS.EMPLOYEE_EXPORT}
                                >
                                    <Button
                                        variant="outline"
                                        onClick={() => setIsExportOpen(true)}
                                    >
                                        <IconDownload className="size-4" />
                                        خروجی
                                    </Button>
                                </PermissionGuard>
                                <PermissionGuard
                                    permission={PERMISSIONS.EMPLOYEE_CREATE}
                                >
                                    <Button
                                        nativeButton={false}
                                        render={
                                            <Link to="/employees/create" />
                                        }
                                    >
                                        <IconPlus className="size-4" />
                                        کارمند جدید
                                    </Button>
                                </PermissionGuard>
                            </div>
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

            <ExportDialog
                open={isExportOpen}
                onOpenChange={setIsExportOpen}
                title="خروجی کارمندان"
                description="خروجی اکسل یا CSV از پروفایل کارمندان"
                fields={exportFields ?? []}
                fieldsLoading={exportFieldsLoading}
                activeFilters={exportFilterSummary}
                showPresentation
                showDetailSheets
                onExport={handleExport}
                onTemplate={downloadEmployeeExportTemplate}
                successMessage="خروجی کارمندان با موفقیت ایجاد شد."
            />
        </>
    );
}
