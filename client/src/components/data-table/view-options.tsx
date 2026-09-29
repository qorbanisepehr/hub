import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import { DropdownMenuCheckboxItem } from "@/components/ui/dropdown-menu";

type ColumnVisibilityProps<TData extends RowData> = {
    table: Table<StockFeatures, TData>;
};

/** Hideable accessor columns of the table — shared source for the desktop
 *  and mobile column-visibility menus, and for deciding whether the
 *  toolbar has any settings content to show at all. */
export function hideableColumns<TData extends RowData>(
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

/**
 * Column show/hide rows as menu items, embedded wherever the shell needs
 * them — the desktop «⚙ تنظیمات جدول» dropdown and the mobile «تنظیمات»
 * submenu. One content, two shells, so both always list the same columns.
 * `DropdownMenuCheckboxItem` so toggling never closes the menu.
 */
export function ColumnVisibilityMenuItems<TData extends RowData>({
    table,
}: ColumnVisibilityProps<TData>) {
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
