import { type ColumnDef } from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconEye, IconPencil, IconSettings } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/data-table";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { RoleBadge } from "@/features/rbac/components/role-badge";
import type { UserListItem } from "@/features/rbac/types";

export const userColumns: ColumnDef<UserListItem>[] = [
    {
        accessorKey: "name",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="نام" />
        ),
        cell: ({ row }) => (
            <Link
                to="/users/$userId"
                params={{ userId: String(row.original.id) }}
                className="text-sm font-medium hover:text-primary transition-colors"
            >
                {row.getValue("name")}
            </Link>
        ),
        enableHiding: false,
    },
    {
        accessorKey: "email",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="ایمیل" />
        ),
        cell: ({ row }) => (
            <span dir="ltr" className="text-sm text-muted-foreground">
                {row.getValue("email")}
            </span>
        ),
    },
    {
        id: "roles",
        accessorFn: (row) => row.roles?.map((r) => r.display_name) ?? [],
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="نقش‌ها" />
        ),
        cell: ({ row }) => {
            const roles = row.original.roles ?? [];
            return (
                <div className="flex flex-wrap gap-1">
                    {roles.length ? (
                        roles.map((role) => (
                            <RoleBadge
                                key={role.id}
                                role={role}
                                active={row.original.active_role?.id === role.id}
                            />
                        ))
                    ) : (
                        <span className="text-muted-foreground text-xs">
                            بدون نقش
                        </span>
                    )}
                </div>
            );
        },
        enableSorting: false,
    },
    {
        id: "actions",
        header: "عملیات",
        cell: ({ row }) => (
            <div className="flex items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    nativeButton={false}
                    render={
                        <Link
                            to="/users/$userId"
                            params={{ userId: String(row.original.id) }}
                        />
                    }
                >
                    <IconEye className="size-4" />
                </Button>
                <PermissionGuard permission="user.update">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        nativeButton={false}
                        render={
                            <Link
                                to="/users/$userId/edit"
                                params={{ userId: String(row.original.id) }}
                            />
                        }
                    >
                        <IconPencil className="size-4" />
                    </Button>
                </PermissionGuard>
                <PermissionGuard permission="user.assign-roles">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        nativeButton={false}
                        render={
                            <Link
                                to="/users/$userId/roles"
                                params={{ userId: String(row.original.id) }}
                            />
                        }
                    >
                        <IconSettings className="size-4" />
                    </Button>
                </PermissionGuard>
            </div>
        ),
        enableSorting: false,
        enableHiding: false,
    },
];
