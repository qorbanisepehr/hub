import {
    type ColumnDef,
    type StockFeatures,
} from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconPencil } from "@tabler/icons-react";
import { Badge } from "@/components/ui/badge";
import { DataTableColumnHeader } from "@/components/data-table";
import { RowActions } from "@/components/shared/row-actions";
import { ActiveBadge } from "@/components/shared/active-badge";
import { PERMISSIONS } from "@/lib/permissions";
import type { Role } from "@/features/rbac/types";

export type RoleActions = {
    onToggle: (role: Role) => void;
    onDelete: (role: Role) => void;
    isToggling?: boolean;
    isDeleting?: boolean;
};

/** One role's commands, shared by the table's action cell and the card. */
export function RoleRowActions({
    role,
    actions,
    variant = "auto",
}: {
    role: Role;
    actions: RoleActions;
    variant?: "auto" | "menu";
}) {
    return (
        <RowActions
            variant={variant}
            stopPropagation={variant === "menu"}
            actions={[
                {
                    icon: <IconPencil className="size-4" />,
                    label: "ویرایش",
                    href: `/roles/${role.id}`,
                    permission: PERMISSIONS.ROLE_UPDATE,
                },
                {
                    type: "switch",
                    checked: role.is_active,
                    onCheckedChange: () => actions.onToggle(role),
                    disabled: actions.isToggling,
                    permission: PERMISSIONS.ROLE_UPDATE,
                },
                {
                    type: "confirm-delete",
                    label: "حذف نقش",
                    message: `آیا از حذف نقش «${role.display_name}» اطمینان دارید؟`,
                    onConfirm: () => actions.onDelete(role),
                    isPending: actions.isDeleting,
                    permission: PERMISSIONS.ROLE_DELETE,
                },
            ]}
        />
    );
}

export function getRoleColumns(
    actions: RoleActions,
): ColumnDef<StockFeatures, Role>[] {
    return [
        {
            accessorKey: "display_name",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="نام نمایشی" />
            ),
            cell: ({ row }) => (
                <Link
                    to="/roles/$roleId"
                    params={{ roleId: String(row.original.id) }}
                    className="text-sm font-medium hover:text-primary transition-colors"
                >
                    {row.getValue("display_name")}
                </Link>
            ),
            meta: { displayName: "نام نمایشی" },
            enableHiding: false,
        },
        {
            accessorKey: "name",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="نام سیستمی" />
            ),
            cell: ({ row }) => (
                <span dir="ltr" className="text-sm text-muted-foreground">
                    {row.getValue("name")}
                </span>
            ),
            meta: { displayName: "نام سیستمی" },
        },
        {
            id: "parent",
            accessorFn: (row) => row.parent?.display_name ?? null,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="نقش والد" />
            ),
            cell: ({ row }) => {
                const parent = row.original.parent;
                return parent ? (
                    <Badge variant="outline">{parent.display_name}</Badge>
                ) : (
                    <span className="text-muted-foreground text-xs">—</span>
                );
            },
            meta: { displayName: "نقش والد" },
        },
        {
            accessorKey: "is_active",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="وضعیت" />
            ),
            cell: ({ row }) => (
                <ActiveBadge isActive={row.getValue("is_active") as boolean} />
            ),
            meta: { displayName: "وضعیت" },
        },
        {
            id: "actions",
            header: "عملیات",
            cell: ({ row }) => (
                <RoleRowActions role={row.original} actions={actions} />
            ),
            enableSorting: false,
            enableHiding: false,
        },
    ];
}
