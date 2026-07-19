import { useParams } from "@tanstack/react-router";

import { UserRoleManager } from "@/features/rbac/components/user-role-manager";
import { PageLayout } from "@/components/shared/page-layout";
import { PageHeader } from "@/components/shared/page-header";

export function UserRolesPage() {
    const { userId } = useParams({ from: "/protected/users/$userId/roles" });
    const userIdNum = Number(userId);

    return (
        <PageLayout>
            <PageHeader
                title="مدیریت نقش کاربر"
                description="تخصیص و مدیریت نقش‌های کاربر"
                backTo={`/users/${userId}`}
            />

            <UserRoleManager userId={userIdNum} />
        </PageLayout>
    );
}
