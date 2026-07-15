import { type ColumnDef } from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconPencil, IconToggleLeft, IconToggleRight, IconTrash } from "@tabler/icons-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/data-table";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import type { Role } from "@/features/rbac/types";

type RoleActions = {
    onToggle: (role: Role) => void;
    onDelete: (role: Role) => void;
};

export function getRoleColumns(actions: RoleActions): ColumnDef<Role>[] {
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
            cell: ({ row }) => {
                const isActive = row.getValue("is_active") as boolean;
                return (
                    <Badge variant={isActive ? "default" : "secondary"}>
                        {isActive ? "فعال" : "غیرفعال"}
                    </Badge>
                );
            },
            meta: { displayName: "وضعیت" },
        },
        {
            id: "actions",
            header: "عملیات",
            cell: ({ row }) => (
                <div className="flex items-center gap-1">
                    <PermissionGuard permission="role.update">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            nativeButton={false}
                            render={
                                <Link
                                    to="/roles/$roleId"
                                    params={{
                                        roleId: String(row.original.id),
                                    }}
                                />
                            }
                        >
                            <IconPencil className="size-4" />
                        </Button>
                    </PermissionGuard>
                    <PermissionGuard permission="role.update">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => actions.onToggle(row.original)}
                        >
                            {row.original.is_active ? (
                                <IconToggleRight className="size-4 text-green-600" />
                            ) : (
                                <IconToggleLeft className="size-4 text-muted-foreground" />
                            )}
                        </Button>
                    </PermissionGuard>
                    <PermissionGuard permission="role.delete">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => actions.onDelete(row.original)}
                        >
                            <IconTrash className="size-4 text-destructive" />
                        </Button>
                    </PermissionGuard>
                </div>
            ),
            enableSorting: false,
            enableHiding: false,
        },
    ];
}
