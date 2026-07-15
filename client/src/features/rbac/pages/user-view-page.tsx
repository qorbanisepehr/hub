import { Link, useParams } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import {
    IconPencil,
    IconBuilding,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { fetchUser } from "@/features/rbac/api";
import { RoleBadge } from "@/features/rbac/components/role-badge";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { ViewSkeleton } from "@/components/shared/view-skeleton";
import { InfoRow } from "@/components/shared/info-row";
import { PageLayout } from "@/components/shared/page-layout";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";

export function UserViewPage() {
    const { userId } = useParams({ from: "/protected/users/$userId" });

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

    if (isLoading) {
        return <ViewSkeleton leftRows={4} rightRows={2} />;
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
                title={user.name}
                description={user.email}
                backTo="/users"
            >
                <div className="flex items-center gap-2">
                    <PermissionGuard permission={["user.update"]}>
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <Link
                                    to="/users/$userId/edit"
                                    params={{ userId: String(user.id) }}
                                />
                            }
                        >
                            <IconPencil className="size-4" />
                            ویرایش
                        </Button>
                    </PermissionGuard>
                    <PermissionGuard permission="user.assign-roles">
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <Link
                                    to="/users/$userId/roles"
                                    params={{ userId: String(user.id) }}
                                />
                            }
                        >
                            مدیریت نقش‌ها
                        </Button>
                    </PermissionGuard>
                </div>
            </PageHeader>

            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>اطلاعات کاربر</CardTitle>
                        <CardDescription>اطلاعات هویتی کاربر</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <InfoRow label="نام" value={user.name} />
                        <InfoRow
                            label="ایمیل"
                            value={<span dir="ltr">{user.email}</span>}
                        />
                        <InfoRow label="تلفن" value={user.phone ?? "—"} />
                        <InfoRow label="نام کاربری" value={user.username ?? "—"} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <IconBuilding className="size-5" />
                            نقش‌ها
                        </CardTitle>
                        <CardDescription>نقش‌های تخصیص داده شده</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {user.roles?.length ? (
                            <div className="flex flex-wrap gap-2">
                                {user.roles.map((role) => (
                                    <RoleBadge
                                        key={role.id}
                                        role={role}
                                        active={user.active_role?.id === role.id}
                                        showActiveLabel
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground text-center py-6">
                                هیچ نقشی تخصیص داده نشده است
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </PageLayout>
    );
}
