import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "@tanstack/react-router";
import { toast } from "sonner";

import { createRole } from "@/features/rbac/api";
import type { CreateRoleData } from "@/features/rbac/types";
import { RoleForm } from "@/features/rbac/components/role-form";
import { getApiError } from "@/lib/error-utils";
import { PageLayout } from "@/components/shared/page-layout";
import { PageHeader } from "@/components/shared/page-header";
import { roleKeys } from "@/lib/query-keys";

export function RoleCreatePage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const createMutation = useMutation({
        mutationFn: (data: CreateRoleData) => createRole(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: roleKeys.all });
            toast.success("نقش با موفقیت ایجاد شد");
            navigate({ to: "/roles" });
        },
        onError: () => {
            toast.error("خطا در ایجاد نقش");
        },
    });

    return (
        <PageLayout>
            <PageHeader
                title="ایجاد نقش جدید"
                description="ایجاد نقش جدید و تخصیص مجوزها"
                backTo="/roles"
            />

            <RoleForm
                onSubmit={(values) => createMutation.mutate(values)}
                isPending={createMutation.isPending}
                error={getApiError(createMutation.error)}
                submitLabel="ایجاد نقش"
            />
        </PageLayout>
    );
}
