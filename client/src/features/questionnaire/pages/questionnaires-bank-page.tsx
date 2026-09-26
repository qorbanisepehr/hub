import { useEffect } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import { IconClipboardText, IconRefresh } from "@tabler/icons-react";

import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { questionnaireBankColumns } from "@/features/questionnaire/columns";
import { fetchQuestionnaires } from "@/features/questionnaire/api";
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
        urlState: {},
    });

    const { ensurePageInRange } = url;

    const { data, isLoading, isError, isFetching } = useQuery({
        queryKey: questionnaireKeys.list({
            page: url.pagination.pageIndex + 1,
            per_page: url.pagination.pageSize,
            sort: url.activeSort?.id,
            order: url.activeSort?.desc ? "desc" : "asc",
            filter: url.globalFilter,
        }),
        queryFn: async () => {
            const { data: response } = await fetchQuestionnaires({
                page: url.pagination.pageIndex + 1,
                per_page: url.pagination.pageSize,
                sort: url.activeSort?.id,
                order: url.activeSort?.desc ? "desc" : "asc",
                filter: url.globalFilter || undefined,
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
                    description="پرسشنامه‌های ارسال‌شده داوطلبان (قابل جستجو بر اساس نام، ایمیل و موبایل)"
                />
            }
            searchPlaceholder="جستجوی پرسشنامه..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
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