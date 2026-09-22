import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { IconTableOptions } from "@tabler/icons-react";

type DataTableViewOptionsProps<TData extends RowData> = {
    table: Table<StockFeatures, TData>;
};

/** Hideable accessor columns of the table — shared source for the desktop
 *  dropdown and the mobile filter sheet. */
function hideableColumns<TData extends RowData>(
    table: Table<StockFeatures, TData>,
) {
    return table
        .getAllColumns()
        .filter(
            (column) =>
                typeof column.accessorFn !== "undefined" &&
                column.getCanHide(),
        );
}

/** Column show/hide rows rendered flat (for the mobile bottom sheet).
 *  Same state source as the desktop dropdown — one behavior, two shells. */
export function ColumnVisibilityList<TData extends RowData>({
    table,
}: DataTableViewOptionsProps<TData>) {
    const columns = hideableColumns(table);
    if (columns.length === 0) return null;

    return (
        <div className="flex flex-col gap-1">
            {columns.map((column) => (
                <label
                    key={column.id}
                    className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                >
                    <Checkbox
                        checked={column.getIsVisible()}
                        onCheckedChange={(value) =>
                            column.toggleVisibility(value === true)
                        }
                    />
                    <span className="min-w-0 truncate">
                        {column.columnDef.meta?.displayName ?? column.id}
                    </span>
                </label>
            ))}
        </div>
    );
}

/** Desktop-only dropdown over the column show/hide list. The mobile toolbar
 *  embeds `ColumnVisibilityList` inside its filter sheet instead. Uses
 *  DropdownMenuCheckboxItem (not plain Checkbox) so toggling never closes
 *  the menu. */
export function DataTableViewOptions<TData extends RowData>({
    table,
}: DataTableViewOptionsProps<TData>) {
    const columns = hideableColumns(table);
    if (columns.length === 0) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="outline"
                        size="icon"
                        className="hidden lg:inline-flex"
                        aria-label="نمایش ستونها"
                    >
                        <IconTableOptions className="size-4" />
                    </Button>
                }
            />
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>
                        نمایش/مخفی کردن ستونها
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {columns.map((column) => (
                        <DropdownMenuCheckboxItem
                            key={column.id}
                            checked={column.getIsVisible()}
                            onCheckedChange={(value: boolean) =>
                                column.toggleVisibility(!!value)
                            }
                        >
                            {column.columnDef.meta?.displayName ?? column.id}
                        </DropdownMenuCheckboxItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
