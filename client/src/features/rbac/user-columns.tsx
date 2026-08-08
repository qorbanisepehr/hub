import { useState } from "react";
import {
    type ColumnDef,
    type StockFeatures,
} from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconMasksTheater, IconPencil, IconUser } from "@tabler/icons-react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { DataTableColumnHeader } from "@/components/data-table";
import { RowActions } from "@/components/shared/row-actions";
import { PERMISSIONS } from "@/lib/permissions";
import { RoleBadge } from "@/features/rbac/components/role-badge";
import { UserRoleManager } from "@/features/rbac/components/user-role-manager";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import type { UserListItem } from "@/features/rbac/types";

function UserAvatar({ name, avatarUrl }: { name: string; avatarUrl: string | null }) {
    const initials = name
        .split(" ")
        .map((w) => w[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();

    return (
        <Avatar size="sm">
            <AvatarImage src={avatarUrl ?? undefined} alt={name} />
            <AvatarFallback>{initials}</AvatarFallback>
        </Avatar>
    );
}

export function getUserColumns(): ColumnDef<StockFeatures, UserListItem>[] {
    return [
        {
            id: "avatar",
            header: "",
            cell: ({ row }) => (
                <UserAvatar
                    name={row.original.name}
                    avatarUrl={row.original.avatar_url}
                />
            ),
            enableSorting: false,
            enableHiding: false,
            size: 40,
        },
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
            meta: { displayName: "نام" },
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
            meta: { displayName: "ایمیل" },
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
            meta: { displayName: "نقش‌ها" },
            enableSorting: false,
        },
        {
            id: "actions",
            header: "عملیات",
            cell: ({ row }) => <UserRowActions user={row.original} />,
            enableSorting: false,
            enableHiding: false,
        },
    ];
}

function UserRowActions({ user }: { user: UserListItem }) {
    const [rolesOpen, setRolesOpen] = useState(false);

    return (
        <>
            <RowActions
                actions={[
                    {
                        icon: <IconUser className="size-4" />,
                        label: "مشاهده",
                        href: `/users/${user.id}`,
                    },
                    {
                        icon: <IconPencil className="size-4" />,
                        label: "ویرایش",
                        href: `/users/${user.id}/edit`,
                        permission: PERMISSIONS.USER_UPDATE,
                    },
                    {
                        icon: <IconMasksTheater className="size-4" />,
                        label: "مدیریت نقش‌ها",
                        onClick: () => setRolesOpen(true),
                        permission: PERMISSIONS.USER_ASSIGN_ROLES,
                    },
                ]}
            />
            <ResponsiveDialog
                open={rolesOpen}
                onOpenChange={setRolesOpen}
                title="مدیریت نقش کاربر"
                description={`تخصیص و مدیریت نقش‌های ${user.name}`}
            >
                <UserRoleManager userId={user.id} />
            </ResponsiveDialog>
        </>
    );
}
