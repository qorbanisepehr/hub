import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import {
    IconCalendarStats,
    IconDownload,
    IconRefresh,
    IconRobot,
    IconRotate,
} from "@tabler/icons-react";

import { DataTablePage } from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import {
    ExportDialog,
    type ExportFormat,
    type ExportPresentation,
} from "@/components/shared";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { boardMeetingKeys } from "@/lib/query-keys";
import { PERMISSIONS } from "@/lib/permissions";
import {
    EXPORT_MIME_TYPES,
    exportDateStamp,
    saveBlobResponse,
} from "@/lib/download";
import { useTagOptions } from "@/features/tags/hooks";
import { exportBoardMeetings, fetchBoardMeetingExportFields } from "../api";
import { boardMeetingColumns } from "../columns";
import { BulkSummaryDialog } from "../components/bulk-summary-dialog";
import { useBoardMeetings, useSyncBoardMeetings } from "../hooks";
import type { BoardMeetingListParams } from "../types";

const route = getRouteApi("/protected/board-meetings");

/**
 * Board meetings list: the shared DataTablePage with URL-driven state.
 * The date window rides the audit-style `date_from`/`date_to` filters
 * (Gregorian from the client, Jalali dates shown).
 *
 * The registry sync sits behind the toolbar's settings gear, not next to
 * «تازه‌سازی»: it reaches out to the source and rewrites registry
 * columns, so it does not belong in the everyday read rhythm. Export is a
 * first-class action — it honors the same filters the list shows.
 */
export function BoardMeetingsPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const [isExportOpen, setIsExportOpen] = useState(false);
    const [isBulkOpen, setIsBulkOpen] = useState(false);

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            columnFilters: [
                {
                    columnId: "date_from",
                    searchKey: "date_from",
                    type: "string",
                },
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

    const { data: exportFields, isLoading: exportFieldsLoading } = useQuery({
        queryKey: ["board-meeting-export-fields"],
        queryFn: async () => {
            const { data: fields } = await fetchBoardMeetingExportFields();
            return fields.data;
        },
        enabled: isExportOpen,
        staleTime: 5 * 60 * 1000,
    });

    const refresh = () =>
        queryClient.invalidateQueries({ queryKey: boardMeetingKeys.all });

    const sync = useSyncBoardMeetings();

    const activeDateFrom = url.activeValue("date_from");
    const activeDateTo = url.activeValue("date_to");
    const activeTagNames = tagOptions
        ?.filter((tag) => tagsFilterValues.includes(tag.slug))
        .map((tag) => tag.name);

    const exportFilterSummary = [
        url.globalFilter ? `جستجو: ${url.globalFilter}` : null,
        activeDateFrom ? `از تاریخ: ${activeDateFrom}` : null,
        activeDateTo ? `تا تاریخ: ${activeDateTo}` : null,
        activeTagNames && activeTagNames.length > 0
            ? `برچسب‌ها: ${activeTagNames.join("، ")}`
            : null,
    ].filter((item): item is string => item !== null);

    const handleExport = async ({
        fields,
        format,
        presentation,
    }: {
        fields: string[];
        format: ExportFormat;
        presentation: ExportPresentation;
    }) => {
        const response = await exportBoardMeetings({
            fields,
            format,
            tags: tagsFilterValues.length > 0 ? tagsFilterValues : undefined,
            date_from: activeDateFrom || undefined,
            date_to: activeDateTo || undefined,
            filter: url.globalFilter || undefined,
            presentation,
        });

        saveBlobResponse(
            response,
            `board-meetings-${exportDateStamp()}.${format}`,
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
                searchPlaceholder="جستجوی تاریخ یا عنوان جلسه..."
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
                        id: "export",
                        label: "خروجی",
                        icon: IconDownload,
                        onClick: () => setIsExportOpen(true),
                        permission: PERMISSIONS.BOARD_MEETING_EXPORT,
                    },
                    {
                        id: "refresh",
                        label: "تازه‌سازی",
                        icon: IconRefresh,
                        onClick: refresh,
                        disabled: isFetching,
                    },
                ]}
                settings={[
                    {
                        id: "sync",
                        label: "همگام‌سازی با منبع",
                        icon: IconRotate,
                        onClick: () => sync.mutate(),
                        disabled: sync.isPending,
                        permission: PERMISSIONS.BOARD_MEETING_SYNC,
                    },
                    {
                        // Next to the sync rather than beside «تازه‌سازی»: it is
                        // the same shape of command — it acts on the registry,
                        // not on the rows on screen, and it is slow.
                        id: "summary-bulk",
                        label: "تولید خلاصه برای جلسات تولیدنشده",
                        icon: IconRobot,
                        onClick: () => setIsBulkOpen(true),
                        permission: PERMISSIONS.BOARD_MEETING_SYNC,
                    },
                ]}
            />

            <BulkSummaryDialog
                open={isBulkOpen}
                onOpenChange={setIsBulkOpen}
                params={params}
            />

            <ExportDialog
                open={isExportOpen}
                onOpenChange={setIsExportOpen}
                title="خروجی جلسات هیئت مدیره"
                description="خروجی اکسل، CSV یا سند چاپی (PDF/Word) از آرشیو جلسات"
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
                successMessage="خروجی جلسات هیئت مدیره با موفقیت ایجاد شد."
            />
        </>
    );
}
