import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { DataTableColumnHeader } from "@/components/data-table";
import { Badge } from "@/components/ui/badge";
import { toPersianDate } from "@/lib/date-format";
import type { BoardMeetingDto } from "./types";

/**
 * Board meeting columns (Phase 3): شماره جلسه / تاریخ جلسه (Jalali,
 * sortable) / مدارک. Titles are the client-facing vocabulary; `pdate` is
 * the canonical key, `number` arrives pre-formatted from the source
 * document filenames.
 */
export const boardMeetingColumns: ColumnDef<StockFeatures, BoardMeetingDto>[] = [
    {
        accessorKey: "number",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="شماره جلسه" />
        ),
        cell: ({ row }) => (
            <span dir="rtl" className="text-sm font-medium">
                {row.original.number ?? "—"}
            </span>
        ),
        meta: { displayName: "شماره جلسه" },
    },
    {
        accessorKey: "pdate",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="تاریخ جلسه" />
        ),
        cell: ({ row }) => (
            <span
                className="text-sm text-muted-foreground"
                title={row.original.pdate}
            >
                {toPersianDate(row.original.date ?? row.original.pdate)}
            </span>
        ),
        meta: { displayName: "تاریخ جلسه" },
    },
    {
        id: "documents",
        enableSorting: false,
        header: () => <span className="text-sm font-medium">مدارک</span>,
        cell: ({ row }) => {
            const count = row.original.documents.length;

            return count > 0 ? (
                <Badge variant="outline">
                    {count.toLocaleString("fa-IR")} مدرک
                </Badge>
            ) : (
                <span className="text-sm text-muted-foreground">—</span>
            );
        },
        meta: { displayName: "مدارک" },
    },
];
