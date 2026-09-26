import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { Badge } from "@/components/ui/badge";
import { DataTableColumnHeader } from "@/components/data-table";
import { toPersianDate, toPersianTime } from "@/lib/date-format";
import {
    CARTABLE_ACTION_LABELS,
    STATUS_CONFIG,
    sourceLabel,
    documentTypeLabel,
} from "./constants";
import { SenderCell } from "./sender-cell";
import type { UnifiedInboxItemDto } from "./types";

/**
 * کارتابل columns per the نمونه کارتابل sample: سامانه / فرستنده (avatar +
 * name + org role) / شماره مدرک / عنوان / نوع مدرک / تاریخ دریافت (Jalali) /
 * کارتابل / وضعیت.
 */
export const unifiedInboxColumns: ColumnDef<StockFeatures, UnifiedInboxItemDto>[] = [
    {
        accessorKey: "source",
        header: () => <span className="text-sm font-medium">سامانه</span>,
        cell: ({ row }) => (
            <Badge variant="outline">{sourceLabel(row.original.source)}</Badge>
        ),
        meta: { displayName: "سامانه" },
    },
    {
        id: "sender",
        header: () => <span className="text-sm font-medium">فرستنده</span>,
        cell: ({ row }) => <SenderCell item={row.original} />,
        meta: { displayName: "فرستنده" },
    },
    {
        accessorKey: "document_number",
        header: () => <span className="text-sm font-medium">شماره مدرک</span>,
        cell: ({ row }) => (
            <span dir="ltr" className="text-sm font-medium">
                {row.original.document_number}
            </span>
        ),
        meta: { displayName: "شماره مدرک" },
    },
    {
        accessorKey: "title",
        header: () => <span className="text-sm font-medium">عنوان</span>,
        cell: ({ row }) => (
            <span className="block max-w-64 truncate text-sm">
                {row.original.title}
            </span>
        ),
        meta: { displayName: "عنوان" },
    },
    {
        accessorKey: "document_type",
        header: () => <span className="text-sm font-medium">نوع مدرک</span>,
        cell: ({ row }) => (
            <Badge variant="secondary">
                {documentTypeLabel(row.original.document_type)}
            </Badge>
        ),
        meta: { displayName: "نوع مدرک" },
    },
    {
        accessorKey: "received_at",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="تاریخ دریافت" />
        ),
        cell: ({ row }) => (
            <span
                className="text-sm text-muted-foreground"
                title={row.original.received_at}
            >
                {toPersianDate(row.original.received_at)}{" "}
                {toPersianTime(row.original.received_at)}
            </span>
        ),
        meta: { displayName: "تاریخ دریافت" },
    },
    {
        accessorKey: "cartable_action",
        header: () => <span className="text-sm font-medium">کارتابل</span>,
        cell: ({ row }) => (
            <Badge variant="outline">
                {CARTABLE_ACTION_LABELS[row.original.cartable_action] ??
                    row.original.cartable_action}
            </Badge>
        ),
        meta: { displayName: "کارتابل" },
    },
    {
        accessorKey: "status",
        header: () => <span className="text-sm font-medium">وضعیت</span>,
        cell: ({ row }) => {
            const config = STATUS_CONFIG[row.original.status] ?? {
                label: row.original.status,
                variant: "secondary" as const,
            };

            return <Badge variant={config.variant}>{config.label}</Badge>;
        },
        meta: { displayName: "وضعیت" },
    },
];
