import type { ReactNode } from "react";
import { Fragment } from "react";
import {
    flexRender,
    type ColumnFiltersState,
    type RowData,
    type StockFeatures,
    type Table as TanStackTable,
} from "@tanstack/react-table";
import type { Icon } from "@tabler/icons-react";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { TableSkeleton } from "@/components/layout";
import { PageLayout } from "@/components/layout";
import { EmptyState } from "@/components/layout";
import { ErrorSection } from "@/components/layout";
import { DataTablePagination } from "./pagination";
import { DataTableToolbar } from "./toolbar";
import { TableFilterBar } from "./table-filter-bar";
import type { ListFilterFieldDef } from "./filter-query-adapter";
import type { DataTableToolbarAction } from "./toolbar-types";

type Meta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
} | undefined;

interface DataTablePageProps<TData extends RowData> {
    table: TanStackTable<StockFeatures, TData>;
    meta?: Meta;
    isLoading?: boolean;
    isError?: boolean;
    title: string;
    description?: string;
    totalLabel?: string;
    icon: Icon;
    header?: ReactNode;
    /** A pre-composed toolbar to render verbatim (replaces the built-in one). */
    toolbar?: ReactNode;
    /** Keep-alive flag: when true the page stays mounted while closed (used by sections). */
    embedded?: boolean;
    // Built-in search + filter bar (DataTablePage owns the toolbar):
    searchPlaceholder?: string;
    globalFilter?: string;
    onGlobalFilterChange?: (value: string) => void;
    columnFilters?: ColumnFiltersState;
    onColumnFiltersChange?: (updater: ColumnFiltersState | ((prev: ColumnFiltersState) => ColumnFiltersState)) => void;
    filterFields?: ListFilterFieldDef[];
    /** Extra controls rendered inline after search/filters (e.g. export, refresh). */
    toolbarActions?: ReactNode;
    /** Descriptor-based actions: one source per table, rendered as desktop
     *  Buttons and mobile «بیشتر» menu items alike through the shared toolbar.
     *  DRY subscription — pages never hand-build per-view wiring. */
    actions?: DataTableToolbarAction[];
    emptyMessage?: string;
    emptyAction?: ReactNode;
    onRetry?: () => void;
    colSpan: number;
    expandedRowIds?: Record<string, boolean>;
    getExpandedRowId?: (row: TData) => string;
    renderExpandedRow?: (row: TData) => ReactNode;
}

export function DataTablePage<TData extends RowData>({
    table,
    meta,
    isLoading = false,
    isError = false,
    title,
    description,
    totalLabel = "مورد",
    icon: Icon,
    header,
    toolbar,
    embedded = false,
    searchPlaceholder,
    globalFilter,
    onGlobalFilterChange,
    columnFilters,
    onColumnFiltersChange,
    filterFields,
    toolbarActions,
    actions,
    emptyMessage = "هیچ موردی یافت نشد",
    emptyAction,
    onRetry,
    colSpan,
    expandedRowIds,
    getExpandedRowId,
    renderExpandedRow,
}: DataTablePageProps<TData>) {
    const composedToolbar =
        toolbar ??
        (searchPlaceholder || filterFields ? (
            <div className="flex flex-wrap items-center gap-2">
                <DataTableToolbar
                    table={table}
                    searchPlaceholder={searchPlaceholder}
                    globalFilter={globalFilter}
                    onGlobalFilterChange={
                        onGlobalFilterChange as (value: string) => void
                    }
                    toolbarActions={toolbarActions}
                    actions={actions}
                    filterBar={
                        filterFields && columnFilters && onColumnFiltersChange ? (
                            <TableFilterBar
                                fields={filterFields}
                                columnFilters={columnFilters}
                                onColumnFiltersChange={onColumnFiltersChange}
                            />
                        ) : undefined
                    }
                />
            </div>
        ) : null);
    const tableBody = isLoading ? (
        <div className="p-4">
            <TableSkeleton />
        </div>
    ) : isError ? (
        <ErrorSection icon={Icon} onRetry={onRetry} />
    ) : (
        <>
            {/* Toolbar stays OUTSIDE the horizontal scroll container so
                filters never scroll away on mobile. */}
            {composedToolbar && (
                <div className="px-4 pt-3 pb-3">{composedToolbar}</div>
            )}
            <div className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="group/row">
                                {headerGroup.headers.map((headerCell) => (
                                    <TableHead
                                        key={headerCell.id}
                                        colSpan={headerCell.colSpan}
                                        className="bg-background group-hover/row:bg-muted"
                                    >
                                        {headerCell.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  headerCell.column.columnDef
                                                      .header,
                                                  headerCell.getContext(),
                                              )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => {
                                // Expansion state can be keyed by a domain id
                                // (stable across pages) or, by default, the
                                // table row index.
                                const expandedRowKey = getExpandedRowId
                                    ? getExpandedRowId(row.original)
                                    : row.id;
                                const isExpanded =
                                    expandedRowIds?.[expandedRowKey] ?? false;
                                const expandedContent =
                                    isExpanded && renderExpandedRow
                                        ? renderExpandedRow(row.original)
                                        : null;

                                return (
                                    <Fragment key={row.id}>
                                        <TableRow className="group/row">
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell
                                                    key={cell.id}
                                                    className="bg-background group-hover/row:bg-muted"
                                                >
                                                    {flexRender(
                                                        cell.column.columnDef
                                                            .cell,
                                                        cell.getContext(),
                                                    )}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                        {expandedContent && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={colSpan}
                                                    className="bg-muted/50 p-0"
                                                >
                                                    {expandedContent}
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                );
                            })
                        ) : (
                            <TableRow>
                                <TableCell colSpan={colSpan} className="h-24 text-center">
                                    <EmptyState icon={Icon} message={emptyMessage}>
                                        {emptyAction}
                                    </EmptyState>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </>
    );

    if (embedded) {
        return (
            <section className="flex flex-col">
                {tableBody}
                {!isLoading && !isError && (
                    <div className="mt-4 border-t pt-3">
                        <DataTablePagination table={table} meta={meta} />
                    </div>
                )}
            </section>
        );
    }

    return (
        <PageLayout>
            {header && (
                <div className="flex flex-wrap items-center justify-between gap-y-3">
                    {header}
                </div>
            )}

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Icon className="size-5" />
                        {title}
                    </CardTitle>
                    {description ?? (meta && (
                        <CardDescription>
                            مجموع {meta.total.toLocaleString("fa-IR")} {totalLabel}
                        </CardDescription>
                    ))}
                </CardHeader>
                <CardContent className="p-0">
                    {tableBody}
                </CardContent>
                {!isLoading && !isError && (
                    <div className="border-t px-4 py-3">
                        <DataTablePagination table={table} meta={meta} />
                    </div>
                )}
            </Card>
        </PageLayout>
    );
}
