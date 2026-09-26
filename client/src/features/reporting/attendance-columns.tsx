import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { DataTableColumnHeader } from "@/components/data-table";
import { toPersianDate, toPersianTime } from "@/lib/date-format";
import type { AttendanceRecordDto } from "./types";

const faNumber = new Intl.NumberFormat("fa-IR");

/** Compact worked-time format for the table cell. */
function formatWorkedMinutes(minutes: number | null): string {
    if (minutes === null) return "—";
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours === 0) return `${faNumber.format(rest)} دقیقه`;
    if (rest === 0) return `${faNumber.format(hours)} ساعت`;
    return `${faNumber.format(hours)} ساعت و ${faNumber.format(rest)} دقیقه`;
}

/** Attendance columns: تاریخ / ورود / خروج / کارکرد — `date` is sortable. */
export const attendanceColumns: ColumnDef<StockFeatures, AttendanceRecordDto>[] = [
    {
        accessorKey: "date",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="تاریخ" />
        ),
        cell: ({ row }) => (
            <span className="text-sm font-medium">
                {toPersianDate(row.original.date)}
            </span>
        ),
        meta: { displayName: "تاریخ" },
    },
    {
        accessorKey: "entry_at",
        header: () => <span className="text-sm font-medium">ورود</span>,
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">
                {toPersianTime(row.original.entry_at)}
            </span>
        ),
        meta: { displayName: "ورود" },
    },
    {
        accessorKey: "exit_at",
        header: () => <span className="text-sm font-medium">خروج</span>,
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">
                {toPersianTime(row.original.exit_at)}
            </span>
        ),
        meta: { displayName: "خروج" },
    },
    {
        accessorKey: "worked_minutes",
        header: () => <span className="text-sm font-medium">کارکرد</span>,
        cell: ({ row }) => (
            <span className="text-sm tabular-nums">
                {formatWorkedMinutes(row.original.worked_minutes)}
            </span>
        ),
        meta: { displayName: "کارکرد" },
    },
];
