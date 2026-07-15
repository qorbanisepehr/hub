import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cn, getPageNumbers } from "@/lib/utils";
import {
    IconChevronLeft,
    IconChevronRight,
    IconChevronsLeft,
    IconChevronsRight,
} from "@tabler/icons-react";
import { type Table } from "@tanstack/react-table";

type DataTablePaginationProps<TData> = {
    table: Table<TData>;
    className?: string;
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export function DataTablePagination<TData>({
    table,
    className,
    meta,
}: DataTablePaginationProps<TData>) {
    const currentPage = table.getState().pagination.pageIndex + 1;
    const totalPages = table.getPageCount();
    const pageNumbers = getPageNumbers(currentPage, totalPages);

    return (
        <div
            className={cn(
                "flex items-center justify-between overflow-clip px-2",
                className,
            )}
            style={{ overflowClipMargin: 1 }}
        >
            <div className="flex w-full items-center gap-2">
                <div className="flex items-center gap-2">
                    <Select
                        value={`${table.getState().pagination.pageSize}`}
                        onValueChange={(value: string | null) => {
                            if (value) table.setPageSize(Number(value));
                        }}
                    >
                        <SelectTrigger className="h-8 w-18">
                            <SelectValue
                                placeholder={
                                    table.getState().pagination.pageSize
                                }
                            />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {[10, 15, 30, 50].map((pageSize) => (
                                <SelectItem
                                    key={pageSize}
                                    value={`${pageSize}`}
                                >
                                    {pageSize}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="hidden text-sm font-medium sm:block">
                        ردیف در هر صفحه
                    </p>
                </div>
                {meta?.total !== undefined && (
                    <div className="text-xs">
                        (<span>تعداد کل:</span>{" "}
                        {meta.total.toLocaleString("fa-IR")})
                    </div>
                )}
            </div>

            <div className="flex items-center sm:space-x-6 lg:space-x-8">
                <div className="flex w-25 items-center justify-center text-sm font-medium">
                    صفحه {currentPage.toLocaleString("fa-IR")} از{" "}
                    {totalPages.toLocaleString("fa-IR")}
                </div>
                <div className="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => table.setPageIndex(0)}
                        disabled={!table.getCanPreviousPage()}
                    >
                        <span className="sr-only">Go to first page</span>
                        <IconChevronsRight className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                    >
                        <span className="sr-only">Go to previous page</span>
                        <IconChevronRight className="size-4" />
                    </Button>

                    {pageNumbers.map((pageNumber, index) => (
                        <div
                            key={`${pageNumber}-${index}`}
                            className="flex items-center"
                        >
                            {pageNumber === "..." ? (
                                <span className="px-1 text-sm text-muted-foreground">
                                    ...
                                </span>
                            ) : (
                                <Button
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
                            )}
                        </div>
                    ))}

                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                    >
                        <span className="sr-only">Go to next page</span>
                        <IconChevronLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() =>
                            table.setPageIndex(table.getPageCount() - 1)
                        }
                        disabled={!table.getCanNextPage()}
                    >
                        <span className="sr-only">Go to last page</span>
                        <IconChevronsLeft className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
