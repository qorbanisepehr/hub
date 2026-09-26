import "@tanstack/react-table";

declare module "@tanstack/react-table" {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<
        TFeatures extends TableFeatures,
        TData extends RowData,
        TValue extends CellData = CellData,
    > {
        displayName?: string;
        /** وضعیت column: render icon-only (compact surfaces) vs icon+label. */
        statusDisplay?: "icon" | "badge";
    }
}
