import { Button } from "@/components/ui/button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    AvatarBadge,
    AvatarGroup,
    AvatarGroupCount,
} from "@/components/ui/avatar";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { assignUserRole } from "@/features/rbac/api";
import { RoleRequirementsView } from "@/features/rbac/components/role-requirements-view";
import { UserSearchSelect } from "@/features/rbac/components/user-search-select";
import { UserAvatar } from "@/components/shared/user-avatar";
import { MATRIX_MANAGER_TYPES } from "@/features/rbac/constants";
import type {
    RoleChartEmployee,
    RoleChartRole,
    RoleChartUser,
} from "@/features/rbac/types";
import { getApiError } from "@/lib/error-utils";
import { PERMISSIONS } from "@/lib/permissions";
import { roleKeys, userKeys } from "@/lib/query-keys";
import { getUserDisplayName } from "@/lib/user-display";
import { cn } from "@/lib/utils";
import {
    IconBriefcase,
    IconBuilding,
    IconDotsVertical,
    IconLoader2,
    IconMasksTheater,
    IconPencil,
    IconPlus,
    IconUserEdit,
    IconUsers,
} from "@tabler/icons-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Link } from "@tanstack/react-router";
import { useState } from "react";
import type { ReactNode } from "react";
import { toast } from "sonner";

/** Header shows the role's members as an overlapping avatar stack. */
const HEADER_AVATAR_LIMIT = 3;

function StatusDot({ active }: { active: boolean }) {
    return (
        <AvatarBadge
            aria-label={active ? "فعال" : "غیرفعال"}
            className={cn(
                "size-2.5 [&>svg]:hidden",
                active ? "bg-success" : "bg-muted-foreground/40",
            )}
        />
    );
}

/**
 * Header identity block, presented exactly like the sidebar NavUser:
 * avatar + name on the first line, the role beneath it. With a single
 * member the member is the face of the role; otherwise the role leads.
 */
function RoleHeaderId({
    role,
    users,
}: {
    role: RoleChartRole;
    users: RoleChartUser[];
}) {
    const statusDot = <StatusDot active={role.is_active} />;

    let avatar: ReactNode;
    let primary: string;
    let secondary: string | null;

    if (users.length === 1) {
        const member = users[0]!;
        const displayName = getUserDisplayName(member);

        avatar = (
            <UserAvatar
                name={displayName}
                avatarUrl={member.avatar_url}
                size="default"
            >
                {statusDot}
            </UserAvatar>
        );
        primary = role.display_name;
        secondary = displayName;
    } else if (users.length > 1) {
        avatar = (
            <AvatarGroup className="shrink-0 [&_[data-slot=avatar]]:size-8">
                {users.slice(0, HEADER_AVATAR_LIMIT).map((user) => (
                    <UserAvatar
                        key={user.id}
                        name={getUserDisplayName(user)}
                        avatarUrl={user.avatar_url}
                        size="default"
                    />
                ))}
                {users.length > HEADER_AVATAR_LIMIT && (
                    <AvatarGroupCount className="size-8 text-[10px]">
                        +{users.length - HEADER_AVATAR_LIMIT}
                    </AvatarGroupCount>
                )}
            </AvatarGroup>
        );
        primary = role.display_name;
        secondary = memberSummary(users);
    } else {
        avatar = (
            <UserAvatar name={role.display_name} size="default">
                {statusDot}
            </UserAvatar>
        );
        primary = role.display_name;
        secondary = "بدون عضو";
    }

    return (
        <div dir="rtl" className="flex min-w-0 items-center gap-2.5 font-sans">
            {avatar}
            <div className="grid min-w-0 flex-1 text-start leading-tight">
                <span className="truncate text-sm font-medium">{primary}</span>
                <span className="truncate text-xs text-foreground/70">
                    {secondary}
                </span>
            </div>
        </div>
    );
}

/**
 * Member names for the header subtitle (employee-aware naming): up to two
 * names, plus a «و N عضو دیگر» suffix beyond that.
 */
function memberSummary(users: RoleChartUser[]): string {
    if (users.length === 0) {
        return "بدون عضو";
    }

    const names = users.map((user) => getUserDisplayName(user));

    if (names.length === 1) {
        return names[0]!;
    }

    const shown = names.slice(0, 2).join("، ");
    const remaining = names.length - Math.min(2, names.length);

    return remaining > 0
        ? `${shown} و ${String(remaining)} عضو دیگر`
        : `${shown} و ${names[1]!}`;
}

function CompactRoleList({
    roles,
    emptyMessage,
}: {
    roles: RoleChartRole[];
    emptyMessage: string;
}) {
    if (roles.length === 0) {
        return (
            <p className="py-2 text-xs text-muted-foreground">{emptyMessage}</p>
        );
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {roles.map((item) => (
                <span
                    key={item.id}
                    className="inline-flex max-w-full items-center rounded-md border bg-muted px-2 py-0.5 text-xs text-card-foreground"
                >
                    <span className="truncate">{item.display_name}</span>
                </span>
            ))}
        </div>
    );
}

/** Member card with the profile facts needed to eyeball شرایط احراز. */
function MemberCard({ user }: { user: RoleChartUser }) {
    const displayName = getUserDisplayName(user);
    const employee = user.employee as RoleChartEmployee | null;
    const educationParts = [employee?.degree, employee?.field_of_study].filter(
        Boolean,
    );

    return (
        <div className="flex items-start gap-3 rounded-xl border bg-card p-3">
            {/* <UserAvatar name={displayName} avatarUrl={user.avatar_url} /> */}
            <div className="min-w-0 flex-1 space-y-0.5">
                <p className="truncate text-sm font-semibold text-card-foreground">
                    {displayName}
                </p>
                {employee?.personnel_code && (
                    <p className="truncate text-xs text-muted-foreground">
                        کدپرسنلی: {employee.personnel_code}
                    </p>
                )}
                {educationParts.length > 0 && (
                    <p className="truncate text-xs text-muted-foreground">
                        {educationParts.join(" • ")}
                    </p>
                )}
                {employee?.org_tenure_years != null && (
                    <p className="truncate text-xs text-muted-foreground">
                        سابقه در سازمان: {employee.org_tenure_years} سال
                    </p>
                )}
            </div>
        </div>
    );
}

export function RoleDetailModal({
    role,
    roles,
    open,
    onOpenChange,
}: {
    role: RoleChartRole | null;
    roles: RoleChartRole[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const queryClient = useQueryClient();
    const [tab, setTab] = useState("info");
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null);

    const assignUserMutation = useMutation({
        mutationFn: (userId: number) => assignUserRole(userId, role!.id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: roleKeys.chart() });
            queryClient.invalidateQueries({ queryKey: roleKeys.all });
            queryClient.invalidateQueries({ queryKey: userKeys.all });
            setSelectedUserId(null);
            toast.success("کاربر به نقش اضافه شد");
        },
        onError: (err) => {
            toast.error(getApiError(err));
        },
    });

    if (!role) {
        return null;
    }

    const parent =
        role.parent_id != null
            ? (roles.find((r) => r.id === role.parent_id) ?? null)
            : null;
    const managedRoles = roles.filter((r) =>
        (r.matrix_manager_roles ?? []).some((m) => m.id === role.id),
    );
    const matrixManagers = role.matrix_manager_roles ?? [];
    const directChildren = roles.filter((r) => r.parent_id === role.id);
    const hasNoMembers = role.users.length === 0;

    return (
        <ResponsiveDialog
            key={role.id}
            open={open}
            onOpenChange={onOpenChange}
            title={<RoleHeaderId role={role} users={role.users} />}
            actions={
                <PermissionGuard permission={PERMISSIONS.ROLE_UPDATE}>
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="عملیات نقش"
                                />
                            }
                        >
                            <IconDotsVertical className="size-4" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                render={
                                    <Link
                                        to="/roles/$roleId"
                                        params={{ roleId: String(role.id) }}
                                    />
                                }
                            >
                                <IconPencil className="size-4" />
                                ویرایش نقش
                            </DropdownMenuItem>
                            {role.users.length === 1 && (
                                <PermissionGuard
                                    permission={PERMISSIONS.USER_UPDATE}
                                >
                                    <DropdownMenuItem
                                        render={
                                            <Link
                                                to="/users/$userId/edit"
                                                params={{
                                                    userId: String(
                                                        role.users[0]!.id,
                                                    ),
                                                }}
                                            />
                                        }
                                    >
                                        <IconUserEdit className="size-4" />
                                        ویرایش کاربر
                                    </DropdownMenuItem>
                                </PermissionGuard>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </PermissionGuard>
            }
        >
            <div dir="rtl" className="space-y-4">
                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger value="info" className="text-xs">
                            اطلاعات نقش
                        </TabsTrigger>
                        <TabsTrigger value="requirements" className="text-xs">
                            شرایط احراز
                        </TabsTrigger>
                        <TabsTrigger value="relations" className="text-xs">
                            روابط
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="info">
                        <div className="space-y-3 pt-3">
                            <div className="flex items-center gap-2.5 rounded-lg border p-2.5">
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <IconBuilding className="size-4 text-primary" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-[10px] text-muted-foreground">
                                        نقش والد
                                    </p>
                                    <span className="block truncate text-sm font-medium text-card-foreground">
                                        {parent?.display_name ?? "ندارد"}
                                    </span>
                                </div>
                            </div>

                            {role.description?.trim() && (
                                <p className="whitespace-pre-line rounded-lg border bg-muted/50 p-3 text-sm leading-relaxed text-muted-foreground">
                                    {role.description}
                                </p>
                            )}

                            <div className="space-y-2">
                                {hasNoMembers ? (
                                    <p className="rounded-lg border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                                        هنوز عضوی به این نقش اختصاص داده نشده
                                        است.
                                    </p>
                                ) : (
                                    role.users.map((user) => (
                                        <MemberCard key={user.id} user={user} />
                                    ))
                                )}
                            </div>

                            {hasNoMembers && (
                                <PermissionGuard
                                    permission={PERMISSIONS.USER_ASSIGN_ROLES}
                                >
                                    <div className="flex items-center gap-2">
                                        <UserSearchSelect
                                            value={selectedUserId}
                                            onChange={(user) =>
                                                setSelectedUserId(
                                                    user?.id ?? null,
                                                )
                                            }
                                            placeholder="افزودن کاربر به این نقش..."
                                            className="w-full"
                                        />
                                        <Button
                                            onClick={() => {
                                                if (selectedUserId != null) {
                                                    assignUserMutation.mutate(
                                                        selectedUserId,
                                                    );
                                                }
                                            }}
                                            disabled={
                                                selectedUserId == null ||
                                                assignUserMutation.isPending
                                            }
                                            size="sm"
                                            className="shrink-0"
                                        >
                                            {assignUserMutation.isPending ? (
                                                <IconLoader2 className="size-4 animate-spin" />
                                            ) : (
                                                <IconPlus className="size-4" />
                                            )}
                                            افزودن کاربر
                                        </Button>
                                    </div>
                                </PermissionGuard>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="requirements">
                        <div className="pt-3">
                            <RoleRequirementsView
                                requirements={role.requirements}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="relations">
                        <div className="space-y-4 pt-3">
                            <div className="space-y-1.5">
                                <p className="text-xs font-semibold text-muted-foreground">
                                    مدیران ماتریسی ({matrixManagers.length})
                                </p>
                                {matrixManagers.length === 0 ? (
                                    <p className="py-1 text-xs text-muted-foreground">
                                        مدیر ماتریسی ندارد.
                                    </p>
                                ) : (
                                    matrixManagers.map((manager) => (
                                        <div
                                            key={`${manager.id}-${manager.manager_type}`}
                                            className="flex items-center gap-2.5 rounded-lg border p-2"
                                        >
                                            <IconMasksTheater className="size-4 shrink-0 text-primary" />
                                            <span className="min-w-0 flex-1 truncate text-sm font-medium text-card-foreground">
                                                {manager.display_name}
                                            </span>
                                            <span className="shrink-0 rounded-md border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">
                                                {MATRIX_MANAGER_TYPES[
                                                    manager.manager_type
                                                ] ?? manager.manager_type}
                                            </span>
                                        </div>
                                    ))
                                )}
                            </div>

                            <Separator />

                            <div className="space-y-1.5">
                                <p className="text-xs font-semibold text-muted-foreground">
                                    زیرمجموعه‌ها ({directChildren.length})
                                </p>
                                <CompactRoleList
                                    roles={directChildren}
                                    emptyMessage="زیرمجموعه‌ای ندارد."
                                />
                            </div>

                            <div className="space-y-1.5">
                                <p className="text-xs font-semibold text-muted-foreground">
                                    مدیریت ماتریسی بر نقش‌ها (
                                    {managedRoles.length})
                                </p>
                                <CompactRoleList
                                    roles={managedRoles}
                                    emptyMessage="بر هیچ نقشی مدیریت ماتریسی ندارد."
                                />
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </ResponsiveDialog>
    );
}
