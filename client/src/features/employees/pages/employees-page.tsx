import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, getRouteApi } from "@tanstack/react-router";
import { IconDownload, IconPlus, IconRefresh, IconUsers } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ExportDialog, type ExportFormat } from "@/components/shared";
import {
    exportEmployees,
    fetchEmployeeExportFields,
    fetchEmployeeExportTemplate,
    fetchEmployees,
} from "@/features/employees/api";
import { employeeColumns } from "@/features/employees/columns";
import { employmentLabels } from "@/features/employees/constants";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { PERMISSIONS } from "@/lib/permissions";
import { employeeKeys } from "@/lib/query-keys";
import {
    saveBlobResponse,
    exportDateStamp,
    EXPORT_MIME_TYPES,
} from "@/lib/download";

const route = getRouteApi("/protected/employees");

async function downloadEmployeeExportTemplate(format: ExportFormat) {
    // The template endpoint only serves the tabular formats; the dialog
    // disables this button while a document format is selected.
    if (format !== "xlsx" && format !== "csv") {
        return;
    }

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
                {
                    columnId: "employment_type",
                    searchKey: "employment_type",
                    type: "string",
                },
                {
                    columnId: "gender",
                    searchKey: "gender",
                    type: "string",
                },
                {
                    columnId: "hire_date_from",
                    searchKey: "hire_date_from",
                    type: "string",
                },
                {
                    columnId: "hire_date_to",
                    searchKey: "hire_date_to",
                    type: "string",
                },
            ],
        },
    });

    const activeStatus = url.activeValue("employment_status");
    const activeStatusNot = url.activeValue("employment_status_not");
    const activeEmploymentType = url.activeValue("employment_type");
    const activeGender = url.activeValue("gender");
    const activeHireDateFrom = url.activeValue("hire_date_from");
    const activeHireDateTo = url.activeValue("hire_date_to");
    const { ensurePageInRange } = url;

    const statusFilterOptions = [
        { label: "فعال", value: "active" },
        { label: "غیرفعال", value: "inactive" },
        { label: "تعلیق", value: "suspended" },
    ];

    const employmentTypeFilterOptions = Object.entries(employmentLabels).map(
        ([value, label]) => ({ value, label }),
    );

    const { data: genderOptions } = useFormOptionsByGroup("gender");
    const genderFilterOptions = (genderOptions ?? []).map((option) => ({
        value: option.value,
        label: option.label,
    }));

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
            employment_type: activeEmploymentType,
            gender: activeGender,
            hire_date_from: activeHireDateFrom,
            hire_date_to: activeHireDateTo,
        }),
        queryFn: async () => {
            const { data: response } = await fetchEmployees({
                page: url.pagination.pageIndex + 1,
                per_page: url.pagination.pageSize,
                sort: url.activeSort?.id,
                order: url.activeSort?.desc ? "desc" : "asc",
                filter: url.globalFilter || undefined,
                status: activeStatus || undefined,
                status_not: activeStatusNot || undefined,
                employment_type: activeEmploymentType || undefined,
                gender: activeGender || undefined,
                hire_date_from: activeHireDateFrom || undefined,
                hire_date_to: activeHireDateTo || undefined,
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
        format: ExportFormat;
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
            EXPORT_MIME_TYPES[format],
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
                    {
                        id: "employment_type",
                        label: "نوع استخدام",
                        type: "select",
                        options: employmentTypeFilterOptions,
                    },
                    {
                        id: "gender",
                        label: "جنسیت",
                        type: "select",
                        options: genderFilterOptions,
                    },
                    {
                        id: "hire_date_from",
                        label: "از تاریخ استخدام",
                        type: "date",
                    },
                    {
                        id: "hire_date_to",
                        label: "تا تاریخ استخدام",
                        type: "date",
                    },
                ]}
                actions={[
                    {
                        id: "export",
                        label: "خروجی",
                        icon: IconDownload,
                        onClick: () => setIsExportOpen(true),
                        permission: PERMISSIONS.EMPLOYEE_EXPORT,
                    },
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
                description="خروجی اکسل، CSV یا سند چاپی (PDF/Word) از پروفایل کارمندان"
                fields={exportFields ?? []}
                fieldsLoading={exportFieldsLoading}
                formats={[
                    { value: "xlsx", label: "اکسل (xlsx)" },
                    { value: "csv", label: "CSV (سازگار با Excel)" },
                    { value: "pdf", label: "سند PDF" },
                    { value: "docx", label: "سند Word" },
                ]}
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
