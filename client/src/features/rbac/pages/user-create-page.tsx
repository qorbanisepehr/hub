import { useNavigate } from "@tanstack/react-router";
import { useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { createUser } from "@/features/rbac/api";
import { UserForm } from "@/features/rbac/components/user-form";
import { getApiError } from "@/lib/error-utils";
import { PageLayout } from "@/components/shared/page-layout";
import { PageHeader } from "@/components/shared/page-header";

export function UserCreatePage() {
    const navigate = useNavigate();

    const mutation = useMutation({
        mutationFn: createUser,
        onSuccess: () => {
            toast.success("کاربر با موفقیت ایجاد شد");
            navigate({ to: "/users" });
        },
        onError: (err: unknown) => {
            toast.error(getApiError(err));
        },
    });

    return (
        <PageLayout>
            <PageHeader
                title="ایجاد کاربر جدید"
                description="اطلاعات کاربر جدید را وارد کنید"
                backTo="/users"
            />

            <UserForm
                onSubmit={(values) => {
                    mutation.mutate({
                        ...values,
                        phone: values.phone || null,
                        username: values.username || null,
                        password: values.password as string,
                        password_confirmation: values.password_confirmation as string,
                    });
                }}
                isPending={mutation.isPending}
                submitLabel="ایجاد کاربر"
                description="فرم ایجاد کاربر جدید در سیستم"
                passwordRequired
            />
        </PageLayout>
    );
}
