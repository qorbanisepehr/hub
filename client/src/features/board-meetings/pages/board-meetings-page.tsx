import { useEffect, useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import {
    IconCalendarMonth,
    IconCalendarStats,
    IconDownload,
    IconLayoutGrid,
    IconRefresh,
    IconRobot,
    IconRotate,
    IconTable,
} from "@tabler/icons-react";

import {
    DataTablePage,
    dataTableSelectColumn,
    type DataTableViewOption,
} from "@/components/data-table";
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
import { jalaliMonthBounds, todayJalaliMonth, type JalaliMonth } from "@/lib/jalali";
import { exportBoardMeetings, fetchBoardMeetingExportFields } from "../api";
import type { RowSelectionState } from "@tanstack/react-table";

import { boardMeetingColumns } from "../columns";
import { BulkSummaryDialog } from "../components/bulk-summary-dialog";
import {
    MeetingCalendar,
    type CalendarSpan,
} from "../components/meeting-calendar";
import { MeetingCard } from "../components/meeting-card";
import { useBoardMeetings, useSyncBoardMeetings } from "../hooks";
import type { BoardMeetingDto, BoardMeetingListParams } from "../types";

/** A month calendar fetches the month at once, not one page of it. */
const CALENDAR_PER_PAGE = 200;

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

    // Which rendering of the same rows is active (D15). URL-driven like the
    // rest of the list state; the calendar view is the only one that needs a
    // month of its own, so it also owns that month locally.
    const view = search.view ?? "table";
    const isCalendarView = view === "calendar";
    const [calendarMonth, setCalendarMonth] = useState<JalaliMonth>(
        todayJalaliMonth,
    );
    // The calendar renders one month or the whole year. The span lives here
    // rather than in the calendar so the fetch below can widen with it.
    const [calendarSpan, setCalendarSpan] = useState<CalendarSpan>("month");
    const calendarBounds = useMemo(
        () => jalaliMonthBounds(calendarMonth),
        [calendarMonth],
    );

    const [isExportOpen, setIsExportOpen] = useState(false);
    const [isBulkOpen, setIsBulkOpen] = useState(false);

    /**
     * Row selection for the table's checkboxes (§15 steps 4–7). Id-keyed
     * through `getRowId` so a tick survives paginating away and back, and
     * cleared when the visible result set changes shape — ids ticked under
     * one filter mix must not be counted under another.
     */
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

    /**
     * Which meetings the summary dialog is preset to, when it was opened
     * from the selection bar. `undefined` means the filter-driven picker
     * (the settings-gear entry) that chooses its own candidates.
     */
    const [bulkPreset, setBulkPreset] = useState<number[] | undefined>(
        undefined,
    );

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

    // The calendar reads a whole month (or year) at once, keyed off the span
    // in view rather than the paged window; the table and card views share the
    // URL-driven page. Tags/search still scope it.
    const params: BoardMeetingListParams = isCalendarView
        ? {
              page: 1,
              per_page: CALENDAR_PER_PAGE,
              sort: "held_on",
              order: "asc",
              filter: url.globalFilter || undefined,
              tags: tagsFilterValues.length > 0 ? tagsFilterValues : undefined,
              // The year view spans twelve months. The endpoint already
              // filters by Jalali year, so ask for the year directly instead
              // of a date window the server would only narrow again.
              ...(calendarSpan === "year"
                  ? { jalali_year: String(calendarMonth.year) }
                  : {
                        date_from: calendarBounds.from,
                        date_to: calendarBounds.to,
                    }),
          }
        : {
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

    // The selection checkbox leads the row; identities come from the row's
    // own `id`, so selection keys do not move with the server's pages.
    const columns = useMemo(
        () => [dataTableSelectColumn<BoardMeetingDto>(), ...boardMeetingColumns],
        [],
    );

    const table = useDataTable({
        columns,
        data: data?.data ?? [],
        meta,
        url,
        enableRowSelection: true,
        rowSelection,
        onRowSelectionChange: setRowSelection,
        getRowId: (row) => String(row.id),
    });

    // URL may hold a page beyond the last one; pull it back into range. The
    // calendar pages a month of its own, so it leaves the URL page alone.
    useEffect(() => {
        if (!isCalendarView && !isLoading && meta) {
            ensurePageInRange(table.getPageCount());
        }
    }, [table, ensurePageInRange, isLoading, meta, isCalendarView]);

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

    // Clear the selection when filters or sorting change: the ticked ids
    // were chosen from one result set, and carrying them into another would
    // make the bar's count a claim about rows nobody can see. Pagination is
    // deliberately not in the signature — paging WITHIN the same filters is
    // the same result set, only seen through a window.
    const selectionScope = [
        url.globalFilter ?? "",
        tagsFilterValues.join(","),
        activeDateFrom ?? "",
        activeDateTo ?? "",
        url.activeSort?.id ?? "",
        url.activeSort?.desc ? "desc" : "asc",
    ].join("|");

    useEffect(() => {
        // The effect's job is "selection outlived its result set" — the
        // string above IS the condition, not a captured value the linter
        // wants enumerated. Cleared unconditionally on first mount too,
        // which is correct: nothing can be ticked before mount.
        setRowSelection({});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectionScope]);

    const selectedIds = Object.keys(rowSelection)
        .filter((id) => rowSelection[id])
        .map(Number);

    // Names for the preset face, as far as the loaded page can supply them. A
    // row ticked on an earlier page is not in the current result window, so
    // the dialog falls back to the id — best-effort, because a second fetch
    // just to label a list would cost a round trip to say what the person
    // already knows.
    const selectedLabels = useMemo(() => {
        const labels = new Map<number, string>();

        for (const row of data?.data ?? []) {
            if (rowSelection[row.id]) {
                labels.set(row.id, row.registry_label);
            }
        }

        return labels;
    }, [data, rowSelection]);
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

    // The shared switcher's options (D15): same rows, three renderings. The
    // page owns what each mode draws (`renderView`); the shell owns the chrome.
    const views: DataTableViewOption[] = useMemo(
        () => [
            { mode: "table", label: "جدول", icon: IconTable },
            { mode: "card", label: "کارتی", icon: IconLayoutGrid },
            { mode: "calendar", label: "تقویم", icon: IconCalendarMonth },
        ],
        [],
    );

    return (
        <>
            <DataTablePage
                table={table}
                meta={meta}
                isLoading={isLoading}
                isError={isError}
                colSpan={boardMeetingColumns.length + 1}
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
                        onClick: () => {
                            setBulkPreset(undefined);
                            setIsBulkOpen(true);
                        },
                        permission: PERMISSIONS.BOARD_MEETING_SYNC,
                    },
                ]}
                bulkActions={[
                    {
                        // The checked-rows path of the same feature: the dialog
                        // opens preset to exactly the ticked meetings, no
                        // candidates list to re-choose from.
                        id: "summary-selected",
                        label: "تولید خلاصه برای انتخاب‌شده‌ها",
                        icon: IconRobot,
                        onClick: () => {
                            setBulkPreset(selectedIds);
                            setIsBulkOpen(true);
                        },
                        permission: PERMISSIONS.BOARD_MEETING_SYNC,
                    },
                ]}
                hidePagination={isCalendarView}
                view={view}
                views={views}
                onViewChange={(mode) => {
                    void navigate({
                        search: (prev) => ({
                            ...prev,
                            view:
                                mode === "table"
                                    ? undefined
                                    : (mode as "card" | "calendar"),
                        }),
                    });
                }}
                renderView={(mode) => {
                    if (mode === "card") {
                        return (
                            <div className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                                {(data?.data ?? []).map((meeting) => (
                                    <MeetingCard
                                        key={meeting.id}
                                        meeting={meeting}
                                    />
                                ))}
                            </div>
                        );
                    }

                    if (mode === "calendar") {
                        return (
                            <div className="p-4">
                                <MeetingCalendar
                                    month={calendarMonth}
                                    onMonthChange={setCalendarMonth}
                                    span={calendarSpan}
                                    onSpanChange={setCalendarSpan}
                                    meetings={data?.data ?? []}
                                />
                            </div>
                        );
                    }

                    return null;
                }}
            />

            <BulkSummaryDialog
                open={isBulkOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setBulkPreset(undefined);
                    }
                    setIsBulkOpen(open);
                }}
                params={params}
                presetMeetingIds={bulkPreset}
                presetLabels={selectedLabels}
                // A queued batch is the same state change the selection stood
                // for, so the checkboxes empty with it.
                onQueued={() => setRowSelection({})}
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
