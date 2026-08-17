import type { ReactNode } from "react";
import {
    flexRender,
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
    toolbar?: ReactNode;
    emptyMessage?: string;
    emptyAction?: ReactNode;
    onRetry?: () => void;
    colSpan: number;
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
    emptyMessage = "هیچ موردی یافت نشد",
    emptyAction,
    onRetry,
    colSpan,
}: DataTablePageProps<TData>) {
    return (
        <PageLayout>
            {header && (
                <div className="flex items-center justify-between">
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
                    {isLoading ? (
                        <div className="p-4">
                            <TableSkeleton />
                        </div>
                    ) : isError ? (
                        <ErrorSection icon={Icon} onRetry={onRetry} />
                    ) : (
                        <div className="overflow-x-auto">
                            {toolbar && (
                                <div className="px-4 pt-3 pb-3">{toolbar}</div>
                            )}
                            <Table>
                                <TableHeader>
                                    {table
                                        .getHeaderGroups()
                                        .map((headerGroup) => (
                                            <TableRow
                                                key={headerGroup.id}
                                                className="group/row"
                                            >
                                                {headerGroup.headers.map(
                                                    (header) => (
                                                        <TableHead
                                                            key={header.id}
                                                            colSpan={
                                                                header.colSpan
                                                            }
                                                            className="bg-background group-hover/row:bg-muted"
                                                        >
                                                            {header.isPlaceholder
                                                                ? null
                                                                : flexRender(
                                                                      header
                                                                          .column
                                                                          .columnDef
                                                                          .header,
                                                                      header.getContext(),
                                                                  )}
                                                        </TableHead>
                                                    ),
                                                )}
                                            </TableRow>
                                        ))}
                                </TableHeader>
                                <TableBody>
                                    {table.getRowModel().rows?.length ? (
                                        table
                                            .getRowModel()
                                            .rows.map((row) => (
                                                <TableRow
                                                    key={row.id}
                                                    className="group/row"
                                                >
                                                    {row
                                                        .getVisibleCells()
                                                        .map((cell) => (
                                                            <TableCell
                                                                key={cell.id}
                                                                className="bg-background group-hover/row:bg-muted"
                                                            >
                                                                {flexRender(
                                                                    cell.column
                                                                        .columnDef
                                                                        .cell,
                                                                    cell.getContext(),
                                                                )}
                                                            </TableCell>
                                                        ))}
                                                </TableRow>
                                            ))
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={colSpan}
                                                className="h-24 text-center"
                                            >
                                                <EmptyState
                                                    icon={Icon}
                                                    message={emptyMessage}
                                                >
                                                    {emptyAction}
                                                </EmptyState>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    )}
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
