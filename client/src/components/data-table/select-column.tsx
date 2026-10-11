import type { ColumnDef, RowData, StockFeatures } from "@tanstack/react-table";

import { Checkbox } from "@/components/ui/checkbox";

/**
 * The selection checkbox column, one definition shared by every table that
 * opts into row selection (`enableRowSelection` on `useDataTable`).
 *
 * Built as a plain function returning a `ColumnDef` rather than a component:
 * column definitions are data, and pages spread this into their column
 * arrays before their own columns so the checkbox leads the row in reading
 * order — which under RTL is also the rightmost column, where a row-level
 * action belongs in this UI.
 *
 * The header checkbox toggles *this page only* (`toggleAllPageRowsSelected`).
 * That is deliberate: the list is server-paginated, so the header has never
 * seen the rows on other pages, and a checkbox that claimed more than it
 * knows would be lying. Cross-page «everything under these filters» stays
 * the job of filter-driven bulk dialogs (e.g. the summary candidates picker),
 * not of a checkbox layer that would have to mirror that query into
 * selection state to keep its count honest.
 *
 * Identities come from the table's `getRowId`, so a selection survives
 * paginating away and back.
 */
export function dataTableSelectColumn<TData extends RowData>(): ColumnDef<
    StockFeatures,
    TData
> {
    return {
        id: "select",
        enableSorting: false,
        enableHiding: false,
        size: 32,
        header: ({ table }) => (
            <Checkbox
                checked={table.getIsAllPageRowsSelected()}
                indeterminate={
                    table.getIsSomePageRowsSelected() &&
                    !table.getIsAllPageRowsSelected()
                }
                onCheckedChange={(value) =>
                    table.toggleAllPageRowsSelected(value === true)
                }
                aria-label="انتخاب همه ردیف‌های این صفحه"
            />
        ),
        cell: ({ row }) => (
            <Checkbox
                checked={row.getIsSelected()}
                disabled={!row.getCanSelect()}
                onCheckedChange={(value) => row.toggleSelected(value === true)}
                aria-label="انتخاب این ردیف"
            />
        ),
    };
}
