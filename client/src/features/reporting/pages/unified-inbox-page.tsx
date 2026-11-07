import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getRouteApi } from "@tanstack/react-router";
import {
    IconBuildingBank,
    IconInbox,
    IconRefresh,
    IconSend,
    IconTag,
} from "@tabler/icons-react";

import {
    DataTablePage,
    type RowAction,
    RecordCard,
    RecordCardGrid,
    TABLE_AND_CARD_VIEWS,
    useListView,
} from "@/components/data-table";
import { ListPageHeader } from "@/components/layout";
import { Badge } from "@/components/ui/badge";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { reportingKeys } from "@/lib/query-keys";
import {
    ALL_SOURCES,
    DOCUMENT_TYPES,
    STATUS_CONFIG,
    documentTypeLabel,
    sourceLabel,
    statusIcon,
    statusLabel,
} from "../constants";
import { unifiedInboxColumns } from "../columns";
import { useUnifiedInbox } from "../hooks";
import type {
    UnifiedInboxListParams,
    UnifiedInboxListResponse,
} from "../types";

const route = getRouteApi("/protected/reports/unified-inbox");

const STATUS_OPTIONS = Object.entries(STATUS_CONFIG).map(([value, config]) => ({
    value,
    label: config.label,
}));

/** Per-source partial-failure warnings (§36) next to the page title. */
function SourceWarnings({
    warnings,
}: {
    warnings: UnifiedInboxListResponse["warnings"];
}) {
    if (warnings.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-2">
            {warnings.map((warning) => (
                <Badge
                    key={`${warning.source ?? "source"}-${warning.code ?? "warning"}`}
                    variant="warning"
                >
                    {warning.source
                        ? `منبع ${sourceLabel(warning.source)} در دسترس نیست`
                        : "هشدار منبع داده"}
                </Badge>
            ))}
        </div>
    );
}

export function UnifiedInboxPage() {
    const queryClient = useQueryClient();
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const listView = useListView({ search, navigate });

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            columnFilters: [
                { columnId: "source", searchKey: "source", type: "string" },
                { columnId: "source_not", searchKey: "source_not", type: "string" },
                { columnId: "type", searchKey: "type", type: "string" },
                { columnId: "type_not", searchKey: "type_not", type: "string" },
                { columnId: "status", searchKey: "status", type: "string" },
                { columnId: "status_not", searchKey: "status_not", type: "string" },
                { columnId: "date_from", searchKey: "date_from", type: "string" },
                { columnId: "date_to", searchKey: "date_to", type: "string" },
            ],
        },
    });

    const { ensurePageInRange } = url;

    const params: UnifiedInboxListParams = {
        page: url.pagination.pageIndex + 1,
        per_page: url.pagination.pageSize,
        sort: url.activeSort?.id,
        order: url.activeSort?.desc ? "desc" : "asc",
        filter: url.globalFilter || undefined,
        source: url.activeValue("source"),
        source_not: url.activeValue("source_not") || undefined,
        type: url.activeValue("type"),
        type_not: url.activeValue("type_not") || undefined,
        status: url.activeValue("status"),
        status_not: url.activeValue("status_not") || undefined,
        date_from: url.activeValue("date_from") || undefined,
        date_to: url.activeValue("date_to") || undefined,
    };

    const { data, isLoading, isError, isFetching } = useUnifiedInbox(params);

    const meta = data?.meta;

    const table = useDataTable({
        columns: unifiedInboxColumns,
        data: data?.data ?? [],
        meta,
        url,
    });

    // URL may hold a page beyond the last one (e.g. after a filter narrows
    // the list); the shared hook pulls it back into range post-load.
    useEffect(() => {
        if (!isLoading && meta) {
            ensurePageInRange(table.getPageCount());
        }
    }, [table, ensurePageInRange, isLoading, meta]);

    const refresh = () =>
        queryClient.invalidateQueries({ queryKey: reportingKeys.all });

    return (
        <DataTablePage
            table={table}
            meta={meta}
            isLoading={isLoading}
            isError={isError}
            title="کارتابل"
            totalLabel="قلم"
            icon={IconInbox}
            header={
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <ListPageHeader
                        title="کارتابل"
                        description="اقلام دریافتی از سامانه‌های خارجی در یک نمای واحد"
                    />
                    <SourceWarnings warnings={data?.warnings ?? []} />
                </div>
            }
            searchPlaceholder="جستجو در عنوان، شماره، فرستنده..."
            globalFilter={url.globalFilter}
            onGlobalFilterChange={url.onGlobalFilterChange}
            columnFilters={url.columnFilters}
            onColumnFiltersChange={url.onColumnFiltersChange}
            filterFields={[
                {
                    id: "source",
                    label: "سامانه",
                    type: "select",
                    options: ALL_SOURCES.map((key) => ({
                        value: key,
                        label: sourceLabel(key),
                    })),
                    negatable: true,
                },
                {
                    id: "type",
                    label: "نوع مدرک",
                    type: "select",
                    options: DOCUMENT_TYPES.map((key) => ({
                        value: key,
                        label: documentTypeLabel(key),
                    })),
                    negatable: true,
                },
                {
                    id: "status",
                    label: "وضعیت",
                    type: "select",
                    options: STATUS_OPTIONS,
                    negatable: true,
                },
                { id: "date_from", label: "از تاریخ", type: "date" },
                { id: "date_to", label: "تا تاریخ", type: "date" },
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
            emptyMessage="اقلامی برای نمایش وجود ندارد"
            onRetry={refresh}
            colSpan={unifiedInboxColumns.length}
            views={TABLE_AND_CARD_VIEWS}
            view={listView.view}
            onViewChange={listView.setView}
            renderView={(mode) =>
                mode === "card" ? (
                    <RecordCardGrid>
                        {(data?.data ?? []).map((item) => (
                            <RecordCard
                                key={item.external_reference}
                                title={item.title}
                                subtitle={
                                    item.sender.display_name ||
                                    item.sender.external_name ||
                                    undefined
                                }
                                icon={statusIcon(item.status)}
                                badges={[
                                    {
                                        label: statusLabel(item.status),
                                        tone:
                                            STATUS_CONFIG[item.status]
                                                ?.variant ?? "secondary",
                                    },
                                    {
                                        label: sourceLabel(item.source),
                                        tone: "outline",
                                    },
                                ]}
                                items={[
                                    {
                                        icon: IconTag,
                                        label: documentTypeLabel(
                                            item.document_type,
                                        ),
                                    },
                                    {
                                        icon: IconBuildingBank,
                                        label: String(item.document_number),
                                    },
                                ]}
                                actions={
                                    item.external_url
                                        ? ([
                                              {
                                                  icon: (
                                                      <IconSend className="size-4" />
                                                  ),
                                                  label: "مشاهده در منبع",
                                                  onClick: () => {
                                                      if (item.external_url) {
                                                          window.open(
                                                              item.external_url,
                                                              "_blank",
                                                              "noopener,noreferrer",
                                                          );
                                                      }
                                                  },
                                              },
                                          ] satisfies RowAction[])
                                        : undefined
                                }
                            />
                        ))}
                    </RecordCardGrid>
                ) : null
            }
        />
    );
}
