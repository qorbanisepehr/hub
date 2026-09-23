import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import { Button } from "@/components/ui/button";
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
 *  view-options dropdown and the mobile «...» submenu. */
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

/** Column show/hide rows as menu items. Shared source for the desktop
 *  view-options dropdown and the mobile «...» submenu — one behavior,
 *  two shells. `DropdownMenuCheckboxItem` so toggling never closes. */
export function ColumnVisibilityMenuItems<TData extends RowData>({
    table,
}: DataTableViewOptionsProps<TData>) {
    return (
        <>
            {hideableColumns(table).map((column) => (
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
        </>
    );
}

/** Desktop-only view-options dropdown. On mobile the same column list is
 *  reached from the toolbar's «...» submenu instead. */
export function DataTableViewOptions<TData extends RowData>(
    props: DataTableViewOptionsProps<TData>,
) {
    if (hideableColumns(props.table).length === 0) return null;

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
                    <ColumnVisibilityMenuItems {...props} />
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
