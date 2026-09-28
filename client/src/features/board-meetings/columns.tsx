import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconEye } from "@tabler/icons-react";
import { DataTableColumnHeader } from "@/components/data-table";
import { RowActions } from "@/components/shared/row-actions";
import { Badge } from "@/components/ui/badge";
import { toPersianDate } from "@/lib/date-format";
import type { BoardMeetingDto } from "./types";

/** «—» for the source's empty meeting numbers (73/214 in the archive). */
function numberOrDash(number: string | null | undefined): string {
    if (number === null || number === undefined || number.trim() === "") {
        return "—";
    }

    return number;
}

/**
 * Board meeting columns (fresh shape): شماره جلسه / تاریخ جلسه (Jalali
 * rendering of the canonical Gregorian date, sortable) / مدارک / عملیات.
 * The date and the row actions open the detail page — same Link-in-cell
 * + RowActions pattern as the users list.
 */
export const boardMeetingColumns: ColumnDef<StockFeatures, BoardMeetingDto>[] = [
    {
        accessorKey: "number",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="شماره جلسه" />
        ),
        cell: ({ row }) => (
            <span dir="rtl" className="text-sm font-medium">
                {numberOrDash(row.original.number)}
            </span>
        ),
        meta: { displayName: "شماره جلسه" },
    },
    {
        accessorKey: "date",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="تاریخ جلسه" />
        ),
        cell: ({ row }) => (
            <Link
                to="/board-meetings/$meetingId"
                params={{ meetingId: String(row.original.id) }}
                className="text-sm text-muted-foreground transition-colors hover:text-primary"
                title={row.original.date ?? undefined}
            >
                {toPersianDate(row.original.date)}
            </Link>
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
    {
        id: "actions",
        header: "عملیات",
        cell: ({ row }) => (
            <RowActions
                actions={[
                    {
                        icon: <IconEye className="size-4" />,
                        label: "مشاهده",
                        href: `/board-meetings/${row.original.id}`,
                    },
                ]}
            />
        ),
        enableSorting: false,
        enableHiding: false,
    },
];
