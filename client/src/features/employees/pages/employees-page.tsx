import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, getRouteApi } from "@tanstack/react-router";
import { IconDownload, IconPlus, IconRefresh, IconUsers } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ExportDialog } from "@/components/shared";
import {
    exportEmployees,
    fetchEmployeeExportFields,
    fetchEmployeeExportTemplate,
    fetchEmployees,
} from "@/features/employees/api";
import { employeeColumns } from "@/features/employees/columns";
import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { useAuthorization } from "@/features/auth";
import { PERMISSIONS } from "@/lib/permissions";
import { employeeKeys } from "@/lib/query-keys";
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
    const { can } = useAuthorization();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const [isExportOpen, setIsExportOpen] = useState(false);

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
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
        },
    });

    const activeStatus = url.activeValue("employment_status");
    const activeStatusNot = url.activeValue("employment_status_not");
    const { ensurePageInRange } = url;

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
        url.globalFilter ? `جستجو: ${url.globalFilter}` : null,
        activeStatusLabel ? `وضعیت: ${activeStatusLabel}` : null,
        activeStatusNotLabel ? `به‌جز وضعیت: ${activeStatusNotLabel}` : null,
    ].filter((f): f is string => f !== null);

    const { data, isLoading, isError, isFetching } = useQuery({
        queryKey: employeeKeys.list({
            page: url.pagination.pageIndex + 1,
            per_page: url.pagination.pageSize,
            sort: url.activeSort?.id,
            order: url.activeSort?.desc ? "desc" : "asc",
            filter: url.globalFilter,
            status: activeStatus,
            status_not: activeStatusNot,
        }),
        queryFn: async () => {
            const { data: response } = await fetchEmployees({
                page: url.pagination.pageIndex + 1,
                per_page: url.pagination.pageSize,
                sort: url.activeSort?.id,
                order: url.activeSort?.desc ? "desc" : "asc",
                filter: url.globalFilter || undefined,
                status: activeStatus,
                status_not: activeStatusNot,
            });
            return response;
        },
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

    const table = useDataTable({
        columns: employeeColumns,
        data: tableData,
        meta,
        url,
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
                        actions={[
                            {
                                label: "کارمند جدید",
                                icon: <IconPlus className="size-4" />,
                                href: "/employees/create",
                                permission: PERMISSIONS.EMPLOYEE_CREATE,
                            },
                        ]}
                    />
                }
                searchPlaceholder="جستجوی کارمند..."
                globalFilter={url.globalFilter}
                onGlobalFilterChange={url.onGlobalFilterChange}
                columnFilters={url.columnFilters}
                onColumnFiltersChange={url.onColumnFiltersChange}
                filterFields={[
                    {
                        id: "employment_status",
                        label: "وضعیت اشتغال",
                        type: "select",
                        options: statusFilterOptions,
                        negatable: true,
                    },
                ]}
                actions={[
                    ...(can(PERMISSIONS.EMPLOYEE_EXPORT)
                        ? [
                              {
                                  id: "export",
                                  label: "خروجی",
                                  icon: IconDownload,
                                  onClick: () => setIsExportOpen(true),
                              },
                          ]
                        : []),
                    {
                        id: "refresh",
                        label: "بازخوانی",
                        icon: IconRefresh,
                        onClick: () =>
                            queryClient.invalidateQueries({
                                queryKey: employeeKeys.all,
                            }),
                        disabled: isFetching,
                    },
                ]}
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
