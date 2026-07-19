import { Link, useParams } from "@tanstack/react-router";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { fetchUser, updateUser } from "@/features/rbac/api";
import { getApiError } from "@/lib/error-utils";
import { UserForm } from "@/features/rbac/components/user-form";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PageLayout } from "@/components/shared/page-layout";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";
import { PageSkeleton } from "@/components/shared/page-skeleton";

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
            <ErrorPage
                status={404}
                title="کاربر مورد نظر یافت نشد"
                homeTo="/users"
                homeLabel="بازگشت به لیست"
            />
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
                    is_active: user.is_active,
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
