import { useEffect } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import { IconClipboardText, IconRefresh } from "@tabler/icons-react";

import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { questionnaireBankColumns } from "@/features/questionnaire/columns";
import { fetchQuestionnaires } from "@/features/questionnaire/api";
import {
    EMPLOYMENT_TYPE_OPTIONS,
    GENDER_OPTIONS,
    MARITAL_STATUS_OPTIONS,
    QUESTIONNAIRE_STATUS_OPTIONS,
    YES_NO_OPTIONS,
} from "@/features/questionnaire/constants";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { questionnaireKeys } from "@/lib/query-keys";

const route = getRouteApi("/protected/questionnaires");

export function QuestionnairesBankPage() {
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
                    columnId: "gender",
                    searchKey: "gender",
                    type: "string",
                },
                {
                    columnId: "marital_status",
                    searchKey: "marital_status",
                    type: "string",
                },
                {
                    columnId: "employment_type",
                    searchKey: "employment_type",
                    type: "string",
                },
                {
                    columnId: "currently_employed",
                    searchKey: "currently_employed",
                    type: "string",
                    serialize: (v) =>
                        v === "true" ? true : v === "false" ? false : undefined,
                    deserialize: (v) =>
                        typeof v === "boolean" ? (v ? "true" : "false") : v,
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

    const { ensurePageInRange } = url;
    const activeStatus = url.activeValue("status");
    const activeStatusNot = url.activeValue("status_not");
    const activeGender = url.activeValue("gender");
    const activeMaritalStatus = url.activeValue("marital_status");
    const activeEmploymentType = url.activeValue("employment_type");
    const activeCurrentlyEmployed = url.activeValue("currently_employed");
    const activeMobileVerified = url.activeValue("mobile_verified");
    const activeEmailVerified = url.activeValue("email_verified");
    const activeDateFrom = url.activeValue("date_from");
    const activeDateTo = url.activeValue("date_to");

    const { data, isLoading, isError, isFetching } = useQuery({
        queryKey: questionnaireKeys.list({
            page: url.pagination.pageIndex + 1,
            per_page: url.pagination.pageSize,
            sort: url.activeSort?.id,
            order: url.activeSort?.desc ? "desc" : "asc",
            filter: url.globalFilter,
            status: activeStatus,
            status_not: activeStatusNot,
            gender: activeGender,
            marital_status: activeMaritalStatus,
            employment_type: activeEmploymentType,
            currently_employed: activeCurrentlyEmployed,
            mobile_verified: activeMobileVerified,
            email_verified: activeEmailVerified,
            date_from: activeDateFrom,
            date_to: activeDateTo,
        }),
        queryFn: async () => {
            const { data: response } = await fetchQuestionnaires({
                page: url.pagination.pageIndex + 1,
                per_page: url.pagination.pageSize,
                sort: url.activeSort?.id,
                order: url.activeSort?.desc ? "desc" : "asc",
                filter: url.globalFilter || undefined,
                status: activeStatus || undefined,
                status_not: activeStatusNot || undefined,
                gender: activeGender || undefined,
                marital_status: activeMaritalStatus || undefined,
                employment_type: activeEmploymentType || undefined,
                currently_employed:
                    activeCurrentlyEmployed === "true"
                        ? true
                        : activeCurrentlyEmployed === "false"
                          ? false
                          : undefined,
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
        columns: questionnaireBankColumns,
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
            title="لیست پرسشنامه‌ها"
            totalLabel="پرسشنامه"
            icon={IconClipboardText}
            header={
                <ListPageHeader
                    title="پرسشنامه‌ها"
                    description="پرسشنامه‌های داوطلبان در همه وضعیت‌ها (قابل فیلتر بر اساس وضعیت، جنسیت، نوع استخدام و تاریخ)"
                />
            }
            searchPlaceholder="جستجوی پرسشنامه..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
                {
                    id: "status",
                    label: "وضعیت",
                    type: "select",
                    options: QUESTIONNAIRE_STATUS_OPTIONS,
                    negatable: true,
                },
                {
                    id: "gender",
                    label: "جنسیت",
                    type: "select",
                    options: GENDER_OPTIONS,
                },
                {
                    id: "marital_status",
                    label: "وضعیت تأهل",
                    type: "select",
                    options: MARITAL_STATUS_OPTIONS,
                },
                {
                    id: "employment_type",
                    label: "نوع استخدام",
                    type: "select",
                    options: EMPLOYMENT_TYPE_OPTIONS,
                },
                {
                    id: "currently_employed",
                    label: "شاغل فعلی",
                    type: "select",
                    options: YES_NO_OPTIONS,
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
                            queryKey: questionnaireKeys.all,
                        }),
                    disabled: isFetching,
                },
            ]}
            emptyAction={null}
            onRetry={() =>
                queryClient.invalidateQueries({
                    queryKey: questionnaireKeys.all,
                })
            }
            colSpan={questionnaireBankColumns.length}
        />
    );
}