import type { ColumnDef } from "@tanstack/react-table";
import { Badge } from "@/components/ui/badge";
import { AUDIT_CATEGORY_LABELS, AUDIT_CATEGORY_VARIANTS, AUDIT_EVENT_LABELS } from "./constants";
import type { AuditLog } from "../types";
import { Link } from "@tanstack/react-router";
import { toPersianDate } from "@/lib/date-format";

export function getAuditLogColumns(): ColumnDef<AuditLog>[] {
    return [
        {
            accessorKey: "event",
            header: "رویداد",
            cell: ({ row }) => {
                const event = row.original.event;
                const label =
                    AUDIT_EVENT_LABELS[event] ?? event;
                return (
                    <Link
                        to="/audit/$logId"
                        params={{ logId: String(row.original.id) }}
                        className="font-medium hover:underline"
                    >
                        {label}
                    </Link>
                );
            },
        },
        {
            accessorKey: "category",
            header: "دسته‌بندی",
            cell: ({ row }) => {
                const category = row.original.category;
                return (
                    <Badge variant={AUDIT_CATEGORY_VARIANTS[category]}>
                        {AUDIT_CATEGORY_LABELS[category] ?? category}
                    </Badge>
                );
            },
        },
        {
            accessorKey: "actor.id",
            header: "کاربر",
            cell: ({ row }) => {
                const actor = row.original.actor;
                if (actor.type === "system") return "سیستم";
                if (!actor.id) return "—";
                return (
                    <div className="flex flex-col">
                        <span>کاربر #{actor.id}</span>
                        {actor.role.name && (
                            <span className="text-xs text-muted-foreground">
                                {actor.role.name}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: "description",
            header: "توضیحات",
            cell: ({ row }) => (
                <span className="line-clamp-1 max-w-[300px]">
                    {row.original.description ?? "—"}
                </span>
            ),
        },
        {
            accessorKey: "ip_address",
            header: "IP",
            cell: ({ row }) => (
                <span className="font-mono text-xs">
                    {row.original.ip_address ?? "—"}
                </span>
            ),
        },
        {
            accessorKey: "created_at",
            header: "تاریخ",
            cell: ({ row }) => (
                <span className="text-sm">
                    {toPersianDate(row.original.created_at)}
                </span>
            ),
        },
    ];
}
