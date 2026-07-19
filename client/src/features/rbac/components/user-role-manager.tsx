import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
    IconCheck,
    IconLoader2,
    IconPlus,
    IconTrash,
} from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    fetchUserRoles,
    assignUserRole,
    removeUserRole,
    switchActiveRole,
} from "@/features/rbac/api";
import { getApiError } from "@/lib/error-utils";
import { RoleSearchSelect } from "@/features/rbac/components/role-search-select";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { useRoles } from "@/features/rbac/hooks/use-roles";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { roleKeys, userKeys } from "@/lib/query-keys";
import type { Role } from "@/features/rbac/types";

type UserRoleManagerProps = {
    userId: number;
    onRolesChanged?: () => void;
};

export function UserRoleManager({
    userId,
    onRolesChanged,
}: UserRoleManagerProps) {
    const queryClient = useQueryClient();
    const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);

    const { data: userRoles, isLoading: rolesLoading } = useQuery({
        queryKey: userKeys.roles(userId),
        queryFn: async () => {
            const { data } = await fetchUserRoles(userId);
            return data;
        },
    });

    const { data: allRoles } = useRoles();

    const assignMutation = useMutation({
        mutationFn: ({ roleId, active }: { roleId: number; active: boolean }) =>
            assignUserRole(userId, roleId, active),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: userKeys.roles(userId) });
            queryClient.invalidateQueries({ queryKey: userKeys.all });
            queryClient.invalidateQueries({ queryKey: userKeys.detail(userId) });
            setSelectedRoleId(null);
            toast.success("نقش با موفقیت تخصیص داده شد");
            onRolesChanged?.();
        },
        onError: (err) => {
            toast.error(getApiError(err));
        },
    });

    const removeMutation = useMutation({
        mutationFn: (roleId: number) => removeUserRole(userId, roleId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: userKeys.roles(userId) });
            queryClient.invalidateQueries({ queryKey: userKeys.all });
            queryClient.invalidateQueries({ queryKey: userKeys.detail(userId) });
            toast.success("نقش از کاربر حذف شد");
            onRolesChanged?.();
        },
        onError: (err) => {
            toast.error(getApiError(err));
        },
    });

    const switchMutation = useMutation({
        mutationFn: (roleId: number) => switchActiveRole(userId, roleId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: userKeys.roles(userId) });
            queryClient.invalidateQueries({ queryKey: userKeys.all });
            queryClient.invalidateQueries({ queryKey: userKeys.detail(userId) });
            toast.success("نقش فعال تغییر کرد");
            onRolesChanged?.();
        },
        onError: (err) => {
            toast.error(getApiError(err));
        },
    });

    const assignedRoleIds = new Set(userRoles?.roles?.map((r) => r.id) ?? []);
    const availableRoles =
        allRoles?.filter((r) => !assignedRoleIds.has(r.id) && r.is_active) ??
        [];

    const handleAssign = () => {
        if (!selectedRoleId) return;
        assignMutation.mutate({ roleId: selectedRoleId, active: false });
    };

    if (rolesLoading) {
        return (
            <div className="space-y-3">
                {Array.from({ length: 3 }).map((_, i) => (
                    <div
                        key={i}
                        className="h-14 animate-pulse rounded-lg bg-muted"
                    />
                ))}
            </div>
        );
    }

    const roles = userRoles?.roles ?? [];
    const activeRoleId = userRoles?.active_role?.id;

    return (
        <Card>
            <CardContent className="p-0">
                {/* Assigned roles list */}
                {roles.length > 0 ? (
                    <div className="divide-y">
                        {roles.map((role) => {
                            const isActive = activeRoleId === role.id;
                            return (
                                <div
                                    key={role.id}
                                    className="relative flex items-center justify-between px-4 py-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <PermissionGuard permission={PERMISSIONS.USER_ASSIGN_ROLES}>
                                            {isActive ? (
                                                <span className="inline-flex size-6 items-center justify-center bg-primary text-primary-foreground rounded">
                                                    <IconCheck className="size-4" />
                                                </span>
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="icon-xs"
                                                    onClick={() =>
                                                        switchMutation.mutate(
                                                            role.id,
                                                        )
                                                    }
                                                    className="cursor-pointer"
                                                >
                                                    <IconCheck className="size-4" />
                                                </Button>
                                            )}
                                        </PermissionGuard>
                                        <span className="text-sm font-medium">
                                            {role.display_name}
                                        </span>
                                    </div>
                                    <div className="relative flex items-center gap-1">
                                        <ConfirmDeleteButton
                                            onConfirm={() =>
                                                removeMutation.mutate(role.id)
                                            }
                                            isPending={removeMutation.isPending}
                                            label=""
                                            confirmLabel=""
                                            cancelLabel=""
                                            size="icon-sm"
                                            variant="ghost"
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground text-center py-8">
                        هیچ نقشی تخصیص داده نشده است
                    </p>
                )}

                {/* Add role section */}
                <PermissionGuard permission={PERMISSIONS.USER_ASSIGN_ROLES}>
                    {roles.length > 0 && <div className="border-t" />}
                    <div className="flex items-center gap-2 px-4 py-3">
                        <RoleSearchSelect
                            value={selectedRoleId}
                            onChange={(role: Role | null) =>
                                setSelectedRoleId(role?.id ?? null)
                            }
                            excludeIds={[...assignedRoleIds]}
                            placeholder="انتخاب نقش..."
                            className="w-full"
                        />
                        <Button
                            onClick={handleAssign}
                            disabled={
                                !selectedRoleId || assignMutation.isPending
                            }
                            size="sm"
                        >
                            {assignMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconPlus className="size-4" />
                            )}
                            تخصیص
                        </Button>
                    </div>
                </PermissionGuard>
            </CardContent>
        </Card>
    );
}
