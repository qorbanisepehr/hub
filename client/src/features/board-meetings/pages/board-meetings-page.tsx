import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import { IconCalendarStats, IconRefresh } from "@tabler/icons-react";

import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { boardMeetingKeys } from "@/lib/query-keys";
import { useTagOptions } from "@/features/tags/hooks";
import { boardMeetingColumns } from "../columns";
import { useBoardMeetings } from "../hooks";
import type { BoardMeetingListParams } from "../types";

const route = getRouteApi("/protected/board-meetings");

/**
 * Board meetings list (Phase 3): the shared DataTablePage with URL-driven
 * state; the optional date window rides the audit-style date_from/date_to
 * filters (Gregorian from the client, Jalali dates shown).
 */
export function BoardMeetingsPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            columnFilters: [
                { columnId: "date_from", searchKey: "date_from", type: "string" },
                { columnId: "date_to", searchKey: "date_to", type: "string" },
                { columnId: "tags", searchKey: "tags", type: "array" },
            ],
        },
    });

    const { ensurePageInRange } = url;

    // AND-filter slugs (A16): every selected tag must be on the meeting.
    const tagSlugsFilter = url.columnFilters.find((f) => f.id === "tags");
    const tagsFilterValues = Array.isArray(tagSlugsFilter?.value)
        ? (tagSlugsFilter.value as string[])
        : [];

    const params: BoardMeetingListParams = {
        page: url.pagination.pageIndex + 1,
        per_page: url.pagination.pageSize,
        sort: url.activeSort?.id,
        order: url.activeSort?.desc ? "desc" : "asc",
        filter: url.globalFilter || undefined,
        tags: tagsFilterValues.length > 0 ? tagsFilterValues : undefined,
        date_from: url.activeValue("date_from") || undefined,
        date_to: url.activeValue("date_to") || undefined,
    };

    const { data, isLoading, isError, isFetching } = useBoardMeetings(params);

    const meta = data?.meta;

    const table = useDataTable({
        columns: boardMeetingColumns,
        data: data?.data ?? [],
        meta,
        url,
    });

    // URL may hold a page beyond the last one; pull it back into range.
    useEffect(() => {
        if (!isLoading && meta) {
            ensurePageInRange(table.getPageCount());
        }
    }, [table, ensurePageInRange, isLoading, meta]);

    const { data: tagOptions } = useTagOptions(undefined);

    const refresh = () =>
        queryClient.invalidateQueries({ queryKey: boardMeetingKeys.all });

    return (
        <DataTablePage
            table={table}
            meta={meta}
            isLoading={isLoading}
            isError={isError}
            colSpan={boardMeetingColumns.length}
            onRetry={refresh}
            title="جلسات هیئت مدیره"
            totalLabel="جلسه"
            icon={IconCalendarStats}
            header={
                <ListPageHeader
                    title="جلسات هیئت مدیره"
                    description="آرشیو جلسات، صورتجلسه‌ها و مصوبات هیئت مدیره"
                />
            }
            searchPlaceholder="جستجوی شماره یا تاریخ جلسه..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
                {
                    id: "tags",
                    label: "برچسب‌ها",
                    type: "multiselect",
                    options: (tagOptions ?? []).map((tag) => ({
                        value: tag.slug,
                        label: tag.name,
                    })),
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
                    label: "تازه‌سازی",
                    icon: IconRefresh,
                    onClick: refresh,
                    disabled: isFetching,
                },
            ]}
        />
    );
}
