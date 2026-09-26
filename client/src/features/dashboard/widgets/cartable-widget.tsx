import { useState } from "react";
import { Link } from "@tanstack/react-router";
import {
    IconArrowLeft,
    IconChevronLeft,
    IconChevronRight,
    IconInbox,
    IconTableOptions,
} from "@tabler/icons-react";
import {
    type ColumnDef,
    type ColumnVisibilityState,
    type SortingState,
    type StockFeatures,
    flexRender,
    stockFeatures,
    useTable,
} from "@tanstack/react-table";
import { cn } from "@/lib/utils";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/layout";
import {
    ColumnVisibilityMenuItems,
} from "@/components/data-table";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useUnifiedInbox } from "@/features/reporting/hooks";
import { unifiedInboxColumns } from "@/features/reporting/columns";
import { SOURCE_LABELS } from "@/features/reporting/constants";
import { PERMISSIONS } from "@/lib/permissions";
import { usePermission } from "@/features/auth";
import type { UnifiedInboxItemDto } from "@/features/reporting/types";

/** Rows the dashboard card shows per page (the full page owns real options). */
const WIDGET_PER_PAGE = 5;

/** «همه» + one pill per known source, mirroring SOURCE_LABELS. */
const SOURCE_PILLS = [
    { value: "all", label: "همه" },
    ...Object.entries(SOURCE_LABELS).map(([value, label]) => ({
        value,
        label,
    })),
];

type UnifiedInboxColumn = ColumnDef<StockFeatures, UnifiedInboxItemDto>;

/**
 * Widget columns: the full cartable columns minus the ones the compact
 * card has no room for (document number, document type) — order preserved.
 */
const HIDDEN_COLUMN_IDS = new Set(["document_number", "document_type"]);

const WIDGET_COLUMNS: UnifiedInboxColumn[] = unifiedInboxColumns.filter(
    (column) => !HIDDEN_COLUMN_IDS.has(column.id ?? ""),
);

/** Skeleton matching the loaded table: 1 header row + N body rows. */
function WidgetTableSkeleton({ rows, cols }: { rows: number; cols: number }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    {Array.from({ length: cols }).map((_col, i) => (
                        // oxlint-disable-next-line react/no-array-index-key -- static skeleton placeholders, no data identity exists
                        <TableHead key={`h-${i}`}>
                            <Skeleton className="h-4 w-20" />
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {Array.from({ length: rows }).map((_row, r) => (
                    // oxlint-disable-next-line react/no-array-index-key -- static skeleton placeholders, no data identity exists
                    <TableRow key={`r-${r}`}>
                        {Array.from({ length: cols }).map((_cell, c) => (
                            // oxlint-disable-next-line react/no-array-index-key -- static skeleton placeholders, no data identity exists
                            <TableCell key={`c-${r}-${c}`}>
                                <Skeleton
                                    className={cn(
                                        "h-5",
                                        c === 0 ? "w-14" : "w-full",
                                    )}
                                />
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/**
 * Compact cartable card: the latest items, permission-gated. State is local
 * (the dashboard has no URL) — a سامانه pill and a lightweight pager feed
 * the same standard list contract the full cartable page speaks; deep
 * interaction stays there via «همه اقلام».
 */
export function CartableWidget() {
    const canView = usePermission(PERMISSIONS.REPORT_UNIFIED_INBOX_VIEW);

    const [source, setSource] = useState("all");
    const [page, setPage] = useState(1);
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnVisibility, setColumnVisibility] =
        useState<ColumnVisibilityState>({});

    const { data, isLoading, isError } = useUnifiedInbox({
        page,
        per_page: WIDGET_PER_PAGE,
        sort: sorting[0]?.id,
        order: sorting[0] ? (sorting[0].desc ? "desc" : "asc") : undefined,
        ...(source !== "all" && { source }),
    });

    const items = data?.data ?? [];
    const lastPage = data?.meta.last_page ?? 1;

    const table = useTable({
        features: stockFeatures,
        data: items,
        columns: WIDGET_COLUMNS,
        state: { sorting, columnVisibility },
        onSortingChange: setSorting,
        onColumnVisibilityChange: setColumnVisibility,
        enableColumnPinning: true,
        manualPagination: true,
        manualSorting: true,
        pageCount: lastPage,
    });

    // After all hooks — the table instance must be built unconditionally.
    if (!canView) return null;

    const handleSourceChange = (value: string) => {
        setSource(value);
        setPage(1);
    };

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    <IconInbox className="size-5" />
                    کارتابل
                </CardTitle>
                <Link
                    to="/reports/unified-inbox"
                    className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    همه اقلام
                    <IconArrowLeft className="size-4" />
                </Link>
            </CardHeader>
            <CardContent className="p-0">
                <div className="border-b px-4 pb-3 pt-1">
                    <Tabs value={source} onValueChange={handleSourceChange}>
                        <TabsList>
                            {SOURCE_PILLS.map((pill) => (
                                <TabsTrigger
                                    key={pill.value}
                                    value={pill.value}
                                    className="px-3 text-xs"
                                >
                                    {pill.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                </div>

                {isLoading ? (
                    <WidgetTableSkeleton rows={5} cols={WIDGET_COLUMNS.length} />
                ) : isError ? (
                    <EmptyState
                        icon={IconInbox}
                        message="دریافت اقلام ناموفق بود"
                    />
                ) : items.length === 0 ? (
                    <EmptyState
                        icon={IconInbox}
                        message={
                            source === "all"
                                ? "اقلامی برای نمایش وجود ندارد"
                                : "قلمی از این سامانه یافت نشد"
                        }
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((headerCell) => (
                                        <TableHead key={headerCell.id}>
                                            {headerCell.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      headerCell.column
                                                          .columnDef.header,
                                                      headerCell.getContext(),
                                                  )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter className="bg-transparent">
                            <TableRow className="hover:bg-transparent">
                                <TableCell colSpan={WIDGET_COLUMNS.length}>
                                    <div className="flex items-center justify-between gap-2">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                render={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7"
                                                        aria-label="نمایش ستونها"
                                                    >
                                                        <IconTableOptions className="size-4" />
                                                    </Button>
                                                }
                                            />
                                            <DropdownMenuContent
                                                align="start"
                                                className="w-48"
                                            >
                                                <DropdownMenuGroup>
                                                    <DropdownMenuLabel>
                                                        نمایش/مخفی کردن ستونها
                                                    </DropdownMenuLabel>
                                                    <DropdownMenuSeparator />
                                                    <ColumnVisibilityMenuItems
                                                        table={table}
                                                    />
                                                </DropdownMenuGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        <div className="flex items-center gap-2">
                                            <p className="text-xs text-muted-foreground">
                                                صفحه {page.toLocaleString("fa-IR")} از{" "}
                                                {Math.max(lastPage, 1).toLocaleString("fa-IR")}
                                            </p>
                                            <div className="flex items-center gap-1">
                                            <Button
                                                variant="outline"
                                                className="size-7 p-0"
                                                onClick={() =>
                                                    setPage((p) => Math.max(1, p - 1))
                                                }
                                                disabled={page <= 1 || isLoading}
                                            >
                                                <span className="sr-only">صفحه قبل</span>
                                                <IconChevronRight className="size-4 ltr:-scale-x-100" />
                                            </Button>
                                            <Button
                                                variant="outline"
                                                className="size-7 p-0"
                                                onClick={() =>
                                                    setPage((p) =>
                                                        Math.min(lastPage, p + 1),
                                                    )
                                                }
                                                disabled={page >= lastPage || isLoading}
                                            >
                                                <span className="sr-only">صفحه بعد</span>
                                                <IconChevronLeft className="size-4 ltr:-scale-x-100" />
                                            </Button>
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
