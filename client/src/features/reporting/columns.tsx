import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { Badge } from "@/components/ui/badge";
import { DataTableColumnHeader } from "@/components/data-table";
import { toPersianDate, toPersianTime } from "@/lib/date-format";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import {
    CARTABLE_ACTION_LABELS,
    STATUS_CONFIG,
    documentTypeLabel,
    sourceLabel,
    statusIcon,
    statusLabel,
    type StatusVariant,
} from "./constants";
import { SenderCell } from "./sender-cell";
import type { UnifiedInboxItemDto } from "./types";

/**
 * کارتابل columns per the نمونه کارتابل sample: وضعیت / سامانه / فرستنده
 * (avatar + name + org role) / شماره مدرک / عنوان / نوع مدرک / تاریخ دریافت
 * (Jalali) / کارتابل.
 *
 * `statusDisplay` on the وضعیت column picks the rendering per surface:
 * "icon" for compact surfaces (dashboard widget — icon + tooltip only),
 * "badge" (icon + label, the default) for the full cartable page.
 */
export const unifiedInboxColumns: ColumnDef<
    StockFeatures,
    UnifiedInboxItemDto
>[] = [
    {
        accessorKey: "status",
        // Not a whitelisted backend sort — header stays a plain title.
        enableSorting: false,
        header: () => <span className="text-sm font-medium">وضعیت</span>,
        cell: ({ row, column }) => {
            const status = row.original.status;
            const variant: StatusVariant =
                STATUS_CONFIG[status]?.variant ?? "secondary";
            const Icon = statusIcon(status);

            if (column.columnDef.meta?.statusDisplay === "icon") {
                return (
                    <Tooltip>
                        <TooltipTrigger
                            render={
                                <span
                                    className="inline-flex text-muted-foreground"
                                    aria-label={statusLabel(status)}
                                >
                                    <Icon className="size-4" />
                                </span>
                            }
                        />
                        <TooltipContent side="top">
                            {statusLabel(status)}
                        </TooltipContent>
                    </Tooltip>
                );
            }

            return (
                <Badge variant={variant}>
                    <Icon className="size-3.5" />
                    {statusLabel(status)}
                </Badge>
            );
        },
        meta: { displayName: "وضعیت", statusDisplay: "badge" },
    },
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
];
