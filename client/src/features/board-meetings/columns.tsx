import { DataTableColumnHeader } from "@/components/data-table";
import { RowActions } from "@/components/shared/row-actions";
import { Badge } from "@/components/ui/badge";
import { toPersianDate } from "@/lib/date-format";
import {
    IconCancel,
    IconCheck,
    IconEye,
    IconMessage2Ai,
} from "@tabler/icons-react";
import { Link } from "@tanstack/react-router";
import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
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
 * distinguishable) / حاضران / عملیات. A مصوبات count used to sit here;
 * the numbers live on the meeting's documents now (vault idea 567), so
 * the list does not repeat them.
 *
 * Only `held_on` is sortable: the backend whitelists `held_on`,
 * `sequence` and `imported_at`, and a column whose id is not on that list
 * would silently fall back to the default sort. Everything derived —
 * the registry label, the attendee count — is pinned to `enableSorting:
 * false` for that reason.
 */
export const boardMeetingColumns: ColumnDef<StockFeatures, BoardMeetingDto>[] =
    [
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
            header: () => (
                <span className="text-sm font-medium">شمارهٔ جلسه</span>
            ),
            cell: ({ row }) => (
                <span dir="ltr" className="text-sm font-medium">
                    {row.original.registry_label}
                </span>
            ),
            meta: { displayName: "شمارهٔ جلسه" },
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
            accessorKey: "tags",
            enableSorting: false,
            header: () => <span className="text-sm font-medium">برچسبها</span>,
            cell: ({ row }) => {
                const visibleTags = row.original.tags.slice(0, 3);
                const hiddenTagCount =
                    row.original.tags.length - visibleTags.length;
                return (
                    <div className="mt-auto flex flex-wrap items-center gap-2">
                        {visibleTags.map((tag) => (
                            <Badge key={tag.slug} variant="outline">
                                {tag.name}
                            </Badge>
                        ))}
                        {hiddenTagCount > 0 && (
                            <Badge variant="secondary">
                                {hiddenTagCount.toLocaleString("fa-IR")}+
                            </Badge>
                        )}
                    </div>
                );
            },
            meta: { displayName: "برچسب‌ها" },
        },
        {
            accessorKey: "has_summary",
            enableSorting: false,
            header: () => (
                <div>
                    <IconMessage2Ai className="size-5 mx-auto" />
                </div>
            ),
            cell: ({ row }) => (
                <div>
                    {row.original.has_summary ? (
                        <IconCheck className="size-5 mx-auto bg-primary text-primary-foreground p-0.5 rounded" />
                    ) : (
                        <IconCancel className="size-5 mx-auto bg-primary text-primary-foreground p-0.5 rounded" />
                    )}
                </div>
            ),
            meta: { displayName: "خلاصه هوشمند" },
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
