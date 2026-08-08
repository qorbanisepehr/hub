import {
    type Column,
    type RowData,
    type StockFeatures,
} from "@tanstack/react-table";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    IconArrowDown,
    IconArrowsSort,
    IconArrowUp,
    IconEyeOff,
} from "@tabler/icons-react";

type DataTableColumnHeaderProps<TData extends RowData, TValue> =
    React.HTMLAttributes<HTMLDivElement> & {
        column: Column<StockFeatures, TData, TValue>;
        title: string;
    };

export function DataTableColumnHeader<TData extends RowData, TValue>({
    column,
    title,
    className,
}: DataTableColumnHeaderProps<TData, TValue>) {
    if (!column.getCanSort()) {
        return <div className={cn(className)}>{title}</div>;
    }

    return (
        <div className={cn("flex items-center space-x-2", className)}>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 data-[state=open]:bg-accent"
                        >
                            <span>{title}</span>
                            {column.getIsSorted() === "desc" ? (
                                <IconArrowDown className="ms-2 size-4" />
                            ) : column.getIsSorted() === "asc" ? (
                                <IconArrowUp className="ms-2 size-4" />
                            ) : (
                                <IconArrowsSort className="ms-2 size-4" />
                            )}
                        </Button>
                    }
                />
                <DropdownMenuContent align="start">
                    <DropdownMenuItem
                        onClick={() => {
                            if (column.getIsSorted() !== "asc") {
                                column.toggleSorting(false);
                            } else {
                                column.clearSorting();
                            }
                        }}
                    >
                        {column.getIsSorted() !== "asc" ? (
                            <IconArrowUp className="size-3.5 text-muted-foreground/70" />
                        ) : (
                            <span className="text-muted-foreground/70">✕</span>
                        )}
                        صعودی
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() => {
                            if (column.getIsSorted() !== "desc") {
                                column.toggleSorting(true);
                            } else {
                                column.clearSorting();
                            }
                        }}
                    >
                        {column.getIsSorted() !== "desc" ? (
                            <IconArrowDown className="size-3.5 text-muted-foreground/70" />
                        ) : (
                            <span className="text-muted-foreground/70">✕</span>
                        )}
                        نزولی
                    </DropdownMenuItem>
                    {column.getCanHide() && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => column.toggleVisibility(false)}
                            >
                                <IconEyeOff className="size-3.5 text-muted-foreground/70" />
                                مخفی کردن
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
