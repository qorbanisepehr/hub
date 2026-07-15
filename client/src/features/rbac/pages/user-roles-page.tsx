import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useParams } from "@tanstack/react-router";
import { IconCheck, IconPlus, IconTrash } from "@tabler/icons-react";
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
import { RoleBadge } from "@/features/rbac/components/role-badge";
import { RoleSearchSelect } from "@/features/rbac/components/role-search-select";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { useRoles } from "@/features/rbac/hooks/use-roles";
import { PageLayout } from "@/components/shared/page-layout";
import { PageHeader } from "@/components/shared/page-header";
import { PageSkeleton } from "@/components/shared/page-skeleton";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import type { Role } from "@/features/rbac/types";

export function UserRolesPage() {
    const { userId } = useParams({ from: "/protected/users/$userId/roles" });
    const userIdNum = Number(userId);
    const queryClient = useQueryClient();
    const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);

    const { data: userRoles, isLoading: rolesLoading } = useQuery({
        queryKey: ["user-roles", userIdNum],
        queryFn: async () => {
            const { data } = await fetchUserRoles(userIdNum);
            return data;
        },
    });

    const { data: allRoles } = useRoles();

    const assignMutation = useMutation({
        mutationFn: ({ roleId, active }: { roleId: number; active: boolean }) =>
            assignUserRole(userIdNum, roleId, active),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["user-roles", userIdNum] });
            queryClient.invalidateQueries({ queryKey: ["users"] });
            setSelectedRoleId(null);
            toast.success("نقش با موفقیت تخصیص داده شد");
        },
        onError: () => {
            toast.error("خطا در تخصیص نقش");
        },
    });

    const removeMutation = useMutation({
        mutationFn: (roleId: number) => removeUserRole(userIdNum, roleId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["user-roles", userIdNum] });
            queryClient.invalidateQueries({ queryKey: ["users"] });
            toast.success("نقش از کاربر حذف شد");
        },
        onError: () => {
            toast.error("خطا در حذف نقش از کاربر");
        },
    });

    const switchMutation = useMutation({
        mutationFn: (roleId: number) => switchActiveRole(userIdNum, roleId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["user-roles", userIdNum] });
            queryClient.invalidateQueries({ queryKey: ["users"] });
            toast.success("نقش فعال تغییر کرد");
        },
        onError: () => {
            toast.error("خطا در تغییر نقش فعال");
        },
    });

    const assignedRoleIds = new Set(userRoles?.roles?.map((r) => r.id) ?? []);
    const availableRoles = allRoles?.filter(
        (r) => !assignedRoleIds.has(r.id) && r.is_active,
    ) ?? [];

    const handleAssign = () => {
        if (!selectedRoleId) return;
        assignMutation.mutate({ roleId: selectedRoleId, active: false });
    };

    if (rolesLoading) {
        return <PageSkeleton />;
    }

    return (
        <PageLayout>
            <PageHeader
                title="مدیریت نقش کاربر"
                description="تخصیص و مدیریت نقش‌های کاربر"
                backTo={`/users/${userId}`}
            />

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">نقش‌های تخصیص داده شده</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {userRoles?.roles?.length ? (
                        userRoles.roles.map((role) => (
                            <div
                                key={role.id}
                                className="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div className="flex items-center gap-3">
                                    <RoleBadge
                                        role={role}
                                        active={userRoles.active_role?.id === role.id}
                                        showActiveLabel
                                    />
                                </div>
                                <div className="flex items-center gap-1">
                                    <PermissionGuard permission="user.assign-roles">
                                        {userRoles.active_role?.id !== role.id && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => switchMutation.mutate(role.id)}
                                            >
                                                <IconCheck className="size-4 ml-1" />
                                                فعال کردن
                                            </Button>
                                        )}
                                        <ConfirmDeleteButton
                                            onConfirm={() => removeMutation.mutate(role.id)}
                                            isPending={removeMutation.isPending}
                                            label=""
                                            confirmLabel="حذف"
                                            size="icon-sm"
                                            variant="ghost"
                                        />
                                    </PermissionGuard>
                                </div>
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-muted-foreground text-center py-6">
                            هیچ نقشی تخصیص داده نشده است
                        </p>
                    )}
                </CardContent>
            </Card>

            <PermissionGuard permission="user.assign-roles">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">تخصیص نقش جدید</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-2">
                            <RoleSearchSelect
                                value={selectedRoleId}
                                onChange={(role: Role | null) =>
                                    setSelectedRoleId(role?.id ?? null)
                                }
                                excludeIds={[...assignedRoleIds]}
                                placeholder="انتخاب نقش..."
                                className="w-[300px]"
                            />
                            <Button
                                onClick={handleAssign}
                                disabled={!selectedRoleId || assignMutation.isPending}
                            >
                                <IconPlus className="size-4 ml-1" />
                                تخصیص
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </PermissionGuard>
        </PageLayout>
    );
}
