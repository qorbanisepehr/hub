import { useEffect } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import { IconFileCv, IconRefresh } from "@tabler/icons-react";

import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { fetchCvBank } from "@/features/cv/api";
import { cvBankColumns } from "@/features/cv/columns";
import { CV_STATUS_OPTIONS, YES_NO_OPTIONS } from "@/features/cv/constants";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { cvKeys } from "@/lib/query-keys";

const route = getRouteApi("/protected/cvs");

export function CvsBankPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            columnFilters: [
                {
                    columnId: "status",
                    searchKey: "status",
                    type: "string",
                },
                {
                    columnId: "status_not",
                    searchKey: "status_not",
                    type: "string",
                },
                {
                    columnId: "mobile_verified",
                    searchKey: "mobile_verified",
                    type: "string",
                    serialize: (v) =>
                        v === "true" ? true : v === "false" ? false : undefined,
                    deserialize: (v) =>
                        typeof v === "boolean" ? (v ? "true" : "false") : v,
                },
                {
                    columnId: "email_verified",
                    searchKey: "email_verified",
                    type: "string",
                    serialize: (v) =>
                        v === "true" ? true : v === "false" ? false : undefined,
                    deserialize: (v) =>
                        typeof v === "boolean" ? (v ? "true" : "false") : v,
                },
                {
                    columnId: "date_from",
                    searchKey: "date_from",
                    type: "string",
                },
                {
                    columnId: "date_to",
                    searchKey: "date_to",
                    type: "string",
                },
            ],
        },
    });

    const activeStatus = url.activeValue("status");
    const activeStatusNot = url.activeValue("status_not");
    const activeMobileVerified = url.activeValue("mobile_verified");
    const activeEmailVerified = url.activeValue("email_verified");
    const activeDateFrom = url.activeValue("date_from");
    const activeDateTo = url.activeValue("date_to");
    const { ensurePageInRange } = url;

    const { data, isLoading, isError, isFetching } = useQuery({
        queryKey: cvKeys.bank({
            page: url.pagination.pageIndex + 1,
            per_page: url.pagination.pageSize,
            sort: url.activeSort?.id,
            order: url.activeSort?.desc ? "desc" : "asc",
            filter: url.globalFilter,
            status: activeStatus,
            status_not: activeStatusNot,
            mobile_verified: activeMobileVerified,
            email_verified: activeEmailVerified,
            date_from: activeDateFrom,
            date_to: activeDateTo,
        }),
        queryFn: async () => {
            const { data: response } = await fetchCvBank({
                page: url.pagination.pageIndex + 1,
                per_page: url.pagination.pageSize,
                sort: url.activeSort?.id,
                order: url.activeSort?.desc ? "desc" : "asc",
                filter: url.globalFilter || undefined,
                status: activeStatus || undefined,
                status_not: activeStatusNot || undefined,
                mobile_verified:
                    activeMobileVerified === "true"
                        ? true
                        : activeMobileVerified === "false"
                          ? false
                          : undefined,
                email_verified:
                    activeEmailVerified === "true"
                        ? true
                        : activeEmailVerified === "false"
                          ? false
                          : undefined,
                date_from: activeDateFrom || undefined,
                date_to: activeDateTo || undefined,
            });
            return response;
        },
    });

    const tableData = data?.data ?? [];
    const meta = data?.meta;

    const table = useDataTable({
        columns: cvBankColumns,
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
            title="لیست رزومه‌ها"
            totalLabel="رزومه"
            icon={IconFileCv}
            header={
                <ListPageHeader
                    title="بانک رزومه"
                    description="همه رزومه‌های داوطلبان (قابل فیلتر بر اساس وضعیت، تأیید موبایل/ایمیل و تاریخ)"
                />
            }
            searchPlaceholder="جستجوی رزومه..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
                {
                    id: "status",
                    label: "وضعیت",
                    type: "select",
                    options: CV_STATUS_OPTIONS,
                    negatable: true,
                },
                {
                    id: "mobile_verified",
                    label: "تأیید موبایل",
                    type: "select",
                    options: YES_NO_OPTIONS,
                },
                {
                    id: "email_verified",
                    label: "تأیید ایمیل",
                    type: "select",
                    options: YES_NO_OPTIONS,
                },
                {
                    id: "date_from",
                    label: "از تاریخ",
                    type: "date",
                },
                {
                    id: "date_to",
                    label: "تا تاریخ",
                    type: "date",
                },
            ]}
            actions={[
                {
                    id: "refresh",
                    label: "بازخوانی",
                    icon: IconRefresh,
                    onClick: () =>
                        queryClient.invalidateQueries({
                            queryKey: cvKeys.all,
                        }),
                    disabled: isFetching,
                },
            ]}
            emptyAction={null}
            onRetry={() =>
                queryClient.invalidateQueries({ queryKey: cvKeys.all })
            }
            colSpan={cvBankColumns.length}
        />
    );
}
