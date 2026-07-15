import { Link, useParams } from "@tanstack/react-router";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { IconPencil } from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { fetchUser, updateUser } from "@/features/rbac/api";
import { getApiError } from "@/lib/error-utils";
import { UserForm } from "@/features/rbac/components/user-form";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PageLayout } from "@/components/shared/page-layout";
import { EmptyState } from "@/components/shared/empty-state";
import { PageHeader } from "@/components/shared/page-header";
import { PageSkeleton } from "@/components/shared/page-skeleton";
import { BackButton } from "@/components/shared/back-button";

export function UserEditPage() {
    const { userId } = useParams({ from: "/protected/users/$userId/edit" });
    const queryClient = useQueryClient();

    const {
        data: user,
        isLoading,
        isError,
    } = useQuery({
        queryKey: ["user", Number(userId)],
        queryFn: async () => {
            const { data } = await fetchUser(Number(userId));
            return data.data;
        },
    });

    const mutation = useMutation({
        mutationFn: (data: Parameters<typeof updateUser>[1]) =>
            updateUser(Number(userId), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["users"] });
            queryClient.invalidateQueries({ queryKey: ["user", Number(userId)] });
            toast.success("اطلاعات کاربر به‌روزرسانی شد");
        },
        onError: () => {
            toast.error("خطا در به‌روزرسانی اطلاعات کاربر");
        },
    });

    if (isLoading) {
        return <PageSkeleton />;
    }

    if (isError || !user) {
        return (
            <EmptyState icon={IconPencil} message="کاربر مورد نظر یافت نشد">
                <BackButton to="/users" label="بازگشت به لیست" />
            </EmptyState>
        );
    }

    return (
        <PageLayout>
            <PageHeader
                title="ویرایش کاربر"
                description={user.name}
                backTo={`/users/${user.id}`}
            />

            <UserForm
                defaultValues={{
                    name: user.name,
                    email: user.email,
                    phone: user.phone ?? "",
                    username: user.username ?? "",
                    password: "",
                    password_confirmation: "",
                }}
                onSubmit={(values) => {
                    const payload: Record<string, unknown> = { ...values };
                    if (!payload.password) {
                        delete payload.password;
                        delete payload.password_confirmation;
                    }
                    mutation.mutate(payload as Parameters<typeof updateUser>[1]);
                }}
                isPending={mutation.isPending}
                error={getApiError(mutation.error)}
                submitLabel="ذخیره تغییرات"
                title="اطلاعات کاربر"
                description="اطلاعات هویتی کاربر را ویرایش کنید"
                passwordSectionLabel="تغییر رمز عبور"
                passwordSectionDescription="در صورت عدم نیاز، فیلدها را خالی بگذارید"
            />
        </PageLayout>
    );
}
