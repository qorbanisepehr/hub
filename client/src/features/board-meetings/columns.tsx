import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconEye } from "@tabler/icons-react";
import { DataTableColumnHeader } from "@/components/data-table";
import { RowActions } from "@/components/shared/row-actions";
import { Badge } from "@/components/ui/badge";
import { toPersianDate } from "@/lib/date-format";
import type { BoardMeetingDto } from "./types";

/**
 * «—» for an empty count, the same way the rest of the app renders a zero.
 */
function countOrDash(count: number): string {
    return count > 0 ? count.toLocaleString("fa-IR") : "—";
}

/**
 * Board meeting columns: تاریخ جلسه (Jalali rendering of the canonical
 * Gregorian date — sortable and the default sort) / شمارهٔ جلسه (the
 * registry key, so an extraordinary second session in a day is
 * distinguishable) / مصوبات / حاضران / عملیات.
 *
 * Only `held_on` is sortable: the backend whitelists `held_on`,
 * `sequence` and `imported_at`, and a column whose id is not on that list
 * would silently fall back to the default sort. Everything derived —
 * the registry label, the two counts — is pinned to `enableSorting:
 * false` for that reason.
 */
export const boardMeetingColumns: ColumnDef<StockFeatures, BoardMeetingDto>[] = [
    {
        accessorKey: "held_on",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="تاریخ جلسه" />
        ),
        cell: ({ row }) => (
            <Link
                to="/board-meetings/$meetingId"
                params={{ meetingId: String(row.original.id) }}
                className="text-sm text-muted-foreground transition-colors hover:text-primary"
                title={row.original.held_on ?? undefined}
            >
                {toPersianDate(row.original.held_on)}
            </Link>
        ),
        meta: { displayName: "تاریخ جلسه" },
    },
    {
        accessorKey: "registry_label",
        enableSorting: false,
        header: () => <span className="text-sm font-medium">شمارهٔ جلسه</span>,
        cell: ({ row }) => (
            <span dir="ltr" className="text-sm font-medium">
                {row.original.registry_label}
            </span>
        ),
        meta: { displayName: "شمارهٔ جلسه" },
    },
    {
        accessorKey: "approval_count",
        enableSorting: false,
        header: () => <span className="text-sm font-medium">مصوبات</span>,
        cell: ({ row }) => {
            const count = row.original.approval_count;

            return count > 0 ? (
                <Badge variant="outline">
                    {count.toLocaleString("fa-IR")} مورد
                </Badge>
            ) : (
                <span className="text-sm text-muted-foreground">—</span>
            );
        },
        meta: { displayName: "مصوبات" },
    },
    {
        accessorKey: "attendee_count",
        enableSorting: false,
        header: () => <span className="text-sm font-medium">حاضران</span>,
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">
                {countOrDash(row.original.attendee_count)}
            </span>
        ),
        meta: { displayName: "حاضران" },
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
