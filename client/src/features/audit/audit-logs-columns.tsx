import type { ColumnDef, StockFeatures } from "@tanstack/react-table";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar";
import { AUDIT_CATEGORY_LABELS, AUDIT_CATEGORY_VARIANTS, AUDIT_EVENT_LABELS } from "./constants";
import type { AuditLog } from "./types";
import { Link } from "@tanstack/react-router";
import { toPersianDate } from "@/lib/date-format";

function ActorCell({ actor }: { actor: AuditLog["actor"] }) {
    if (actor.type === "system") return <span className="text-muted-foreground">سیستم</span>;
    if (!actor.id) return <span>—</span>;

    const displayName = actor.display_name ?? actor.name ?? `کاربر #${actor.id}`;
    const initials = displayName
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);

    return (
        <div className="flex items-center gap-2">
            <Avatar className="size-8">
                {actor.avatar_url && (
                    <AvatarImage src={actor.avatar_url} alt={displayName} />
                )}
                <AvatarFallback className="text-xs">{initials}</AvatarFallback>
            </Avatar>
            <div className="flex flex-col min-w-0">
                <span className="truncate font-medium text-sm">{displayName}</span>
                {actor.role.name && (
                    <span className="text-xs text-muted-foreground truncate">
                        {actor.role.name}
                    </span>
                )}
            </div>
        </div>
    );
}

export function getAuditLogColumns(): ColumnDef<StockFeatures, AuditLog>[] {
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
            cell: ({ row }) => <ActorCell actor={row.original.actor} />,
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
