import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useNavigate, useParams } from "@tanstack/react-router";
import { toast } from "sonner";

import { fetchRole, updateRole } from "@/features/rbac/api";
import type { UpdateRoleData } from "@/features/rbac/types";
import { RoleForm } from "@/features/rbac/components/role-form";
import { getApiError } from "@/lib/error-utils";
import { PageLayout } from "@/components/shared/page-layout";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";
import { PageSkeleton } from "@/components/shared/page-skeleton";
import { roleKeys } from "@/lib/query-keys";

export function RoleEditPage() {
    const { roleId } = useParams({ from: "/protected/roles/$roleId" });
    const roleIdNum = Number(roleId);
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const { data: roleData, isLoading: roleLoading, isError } = useQuery({
        queryKey: roleKeys.detail(roleIdNum),
        queryFn: async () => {
            const { data } = await fetchRole(roleIdNum);
            return data;
        },
    });

    const updateMutation = useMutation({
        mutationFn: (data: UpdateRoleData) => updateRole(roleIdNum, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: roleKeys.all });
            queryClient.invalidateQueries({ queryKey: roleKeys.detail(roleIdNum) });
            toast.success("نقش با موفقیت به‌روزرسانی شد");
            navigate({ to: "/roles" });
        },
        onError: (err: unknown) => {
            toast.error(getApiError(err));
        },
    });

    if (roleLoading) {
        return <PageSkeleton />;
    }

    if (isError) {
        return (
            <ErrorPage
                title="خطا در بارگذاری اطلاعات نقش"
                homeTo="/roles"
                homeLabel="بازگشت به لیست"
            />
        );
    }

    const role = roleData?.data;

    if (!role) {
        return (
            <ErrorPage
                status={404}
                title="نقش مورد نظر یافت نشد"
                homeTo="/roles"
                homeLabel="بازگشت به لیست"
            />
        );
    }

    // Parent roles that would cause cycles (role's own id + immediate children)
    const excludedParentIds = [roleIdNum];
    if (role?.children) {
        for (const child of role.children) {
            excludedParentIds.push(child.id);
        }
    }

    return (
        <PageLayout>
            <PageHeader
                title={`ویرایش نقش: ${role?.display_name}`}
                backTo="/roles"
            />

            <RoleForm
                defaultValues={{
                    name: role?.name ?? "",
                    display_name: role?.display_name ?? "",
                    description: role?.description ?? "",
                    parent_id: role?.parent_id ?? null,
                    inherits_permissions: role?.inherits_permissions ?? false,
                    is_active: role?.is_active ?? true,
                    access_rules:
                        role?.access_rules?.map((rule) => ({
                            permission_id: rule.permission_id,
                            effect: rule.effect,
                            priority: rule.priority,
                            is_active: rule.is_active,
                            policy: rule.policy,
                        })) ?? [],
                    matrix_managers: role?.matrix_managers ?? [],
                    requirements: {
                        min_education: role?.requirements?.min_education ?? null,
                        min_experience_years:
                            role?.requirements?.min_experience_years ?? null,
                        required_skills: role?.requirements?.required_skills ?? [],
                        preferred_skills:
                            role?.requirements?.preferred_skills ?? [],
                        certifications: role?.requirements?.certifications ?? [],
                        languages: role?.requirements?.languages ?? [],
                    },
                }}
                onSubmit={(values) => updateMutation.mutate(values)}
                isPending={updateMutation.isPending}
                error={getApiError(updateMutation.error)}
                submitLabel="ذخیره تغییرات"
                excludeParentIds={excludedParentIds}
                inheritedPermissionIds={(() => {
                    if (!role?.parent || !role.inherits_permissions) return [];
                    const parentPerms = role.parent.permissions?.map((p) => p.id) ?? [];
                    const parentGroups = role.parent.permission_groups ?? [];
                    const groupPerms = parentGroups.flatMap((g) =>
                        (g.permissions ?? []).map((p) => p.id),
                    );
                    return [...new Set([...parentPerms, ...groupPerms])];
                })()}
            />
        </PageLayout>
    );
}
