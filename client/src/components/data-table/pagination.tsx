import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cn, getPageNumbers } from "@/lib/utils";
import { PAGINATION } from "@/lib/constants";
import {
    IconChevronLeft,
    IconChevronRight,
    IconChevronsLeft,
    IconChevronsRight,
} from "@tabler/icons-react";
import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";

type DataTablePaginationProps<TData extends RowData> = {
    table: Table<StockFeatures, TData>;
    className?: string;
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

/**
 * Shared list footer, responsive by CSS only (like the toolbar):
 *
 * - **Mobile:** the essentials — rows-per-page + total on one side, and a
 *   compact «صفحه X از Y» counter with prev/next on the other. Wraps to two
 *   rows before anything gets clipped.
 * - **sm+:** first/last jump buttons appear.
 * - **lg+:** the numbered page buttons appear too.
 *
 * All spacing uses logical `gap` (never physical `space-x-*`) so RTL never
 * collapses the wrong side.
 */
export function DataTablePagination<TData extends RowData>({
    table,
    className,
    meta,
}: DataTablePaginationProps<TData>) {
    const currentPage = table.store.state.pagination.pageIndex + 1;
    const totalPages = table.getPageCount();
    const pageNumbers = getPageNumbers(currentPage, totalPages);

    return (
        <div
            className={cn(
                "flex flex-wrap items-center justify-between gap-x-4 gap-y-2 overflow-clip px-2",
                className,
            )}
            style={{ overflowClipMargin: 1 }}
        >
            <div className="flex items-center gap-2">
                <Select
                    value={`${table.store.state.pagination.pageSize}`}
                    onValueChange={(value: string | null) => {
                        if (value) table.setPageSize(Number(value));
                    }}
                >
                    <SelectTrigger className="h-8 w-18">
                        <SelectValue
                            placeholder={
                                table.store.state.pagination.pageSize
                            }
                        />
                    </SelectTrigger>
                    <SelectContent side="top">
                        {PAGINATION.PAGE_SIZE_OPTIONS.map((pageSize) => (
                            <SelectItem key={pageSize} value={`${pageSize}`}>
                                {pageSize}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <p className="hidden text-sm font-medium sm:block">
                    ردیف در هر صفحه
                </p>
                {meta?.total !== undefined && (
                    <div className="text-xs text-muted-foreground">
                        <span className="hidden sm:inline">تعداد کل: </span>
                        <span className="sm:hidden">مجموع: </span>
                        {meta.total.toLocaleString("fa-IR")}
                    </div>
                )}
            </div>

            <div className="flex items-center gap-2">
                <div className="flex items-center justify-center text-sm font-medium">
                    صفحه {currentPage.toLocaleString("fa-IR")} از{" "}
                    {Math.max(totalPages, 1).toLocaleString("fa-IR")}
                </div>
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        className="hidden size-8 p-0 sm:inline-flex"
                        onClick={() => table.setPageIndex(0)}
                        disabled={!table.getCanPreviousPage()}
                    >
                        <span className="sr-only">Go to first page</span>
                        <IconChevronsRight className="size-4 ltr:-scale-x-100" />
                    </Button>
                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                    >
                        <span className="sr-only">Go to previous page</span>
                        <IconChevronRight className="size-4 ltr:-scale-x-100" />
                    </Button>

                    {/* Numbered pages only have room on wide screens. */}
                    <div className="hidden items-center gap-1 lg:flex">
                        {pageNumbers.map((pageNumber, index) =>
                            pageNumber === "..." ? (
                                // oxlint-disable-next-line react/no-array-index-key -- "..." placeholders can repeat, so index must stay part of the key
                                <span key={`dots-${index}`} className="px-1 text-sm text-muted-foreground">
                                    ...
                                </span>
                            ) : (
                                <Button
                                    key={pageNumber}
                                    variant={
                                        currentPage === pageNumber
                                            ? "default"
                                            : "outline"
                                    }
                                    className="h-8 min-w-8 px-2"
                                    onClick={() =>
                                        table.setPageIndex(
                                            (pageNumber as number) - 1,
                                        )
                                    }
                                >
                                    <span className="sr-only">
                                        Go to page {pageNumber}
                                    </span>
                                    {pageNumber}
                                </Button>
                            ),
                        )}
                    </div>

                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                    >
                        <span className="sr-only">Go to next page</span>
                        <IconChevronLeft className="size-4 ltr:-scale-x-100" />
                    </Button>
                    <Button
                        variant="outline"
                        className="hidden size-8 p-0 sm:inline-flex"
                        onClick={() =>
                            table.setPageIndex(table.getPageCount() - 1)
                        }
                        disabled={!table.getCanNextPage()}
                    >
                        <span className="sr-only">Go to last page</span>
                        <IconChevronsLeft className="size-4 ltr:-scale-x-100" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
