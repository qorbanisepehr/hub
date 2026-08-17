import { useState } from "react";
import { Link, useParams } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import {
    IconPencil,
    IconMasksTheater,
    IconShieldCheck,
} from "@tabler/icons-react";
import { isAxiosError } from "axios";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { fetchUser } from "@/features/rbac/api";
import { getApiError } from "@/lib/error-utils";
import { RoleBadge } from "@/features/rbac/components/role-badge";
import { UserRoleManager } from "@/features/rbac/components/user-role-manager";
import { EffectivePermissionsView } from "@/features/rbac/components/effective-permissions-view";
import { getUserDisplayName } from "@/lib/user-display";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { ViewSkeleton } from "@/components/layout";
import { InfoRow } from "@/components/shared/info-row";
import { PageLayout } from "@/components/layout";
import { ErrorPage } from "@/components/layout";
import { PageHeader } from "@/components/layout";
import { userKeys } from "@/lib/query-keys";

export function UserViewPage() {
    const { userId } = useParams({ from: "/protected/users/$userId" });
    const [rolesOpen, setRolesOpen] = useState(false);

    const {
        data: user,
        isLoading,
        isError,
        error,
        refetch,
    } = useQuery({
        queryKey: userKeys.detail(Number(userId)),
        queryFn: async () => {
            const { data } = await fetchUser(Number(userId));
            return data.data;
        },
    });

    if (isLoading) {
        return <ViewSkeleton leftRows={4} rightRows={2} />;
    }

    if (isError) {
        const status = isAxiosError(error) ? error.response?.status : undefined;

        return (
            <ErrorPage
                status={status}
                title={getApiError(error) ?? undefined}
                homeTo="/users"
                homeLabel="بازگشت به لیست"
            />
        );
    }

    if (!user) {
        return (
            <ErrorPage
                status={404}
                title="کاربر مورد نظر یافت نشد"
                homeTo="/users"
                homeLabel="بازگشت به لیست"
            />
        );
    }

    const displayName = getUserDisplayName(user);

    return (
        <PageLayout>
            <PageHeader
                title={displayName}
                description={user.email}
                backTo="/users"
            >
                <div className="flex items-center gap-2">
                    <PermissionGuard permission={[PERMISSIONS.USER_UPDATE]}>
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
                    <PermissionGuard permission={PERMISSIONS.USER_ASSIGN_ROLES}>
                        <Button
                            variant="outline"
                            onClick={() => setRolesOpen(true)}
                        >
                            <IconMasksTheater className="size-4" />
                            مدیریت نقش‌ها
                        </Button>
                    </PermissionGuard>
                </div>
            </PageHeader>

            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <Avatar size="lg">
                                <AvatarImage src={user.avatar_url ?? undefined} alt={displayName} />
                                <AvatarFallback>
                                    {displayName.split(" ").map((w) => w[0]).slice(0, 2).join("").toUpperCase()}
                                </AvatarFallback>
                            </Avatar>
                            <div className="space-y-0.5">
                                <CardTitle>{displayName}</CardTitle>
                                <div className="flex items-center gap-2">
                                    {user.employee && (
                                        <Badge variant="outline">
                                            کارمند
                                        </Badge>
                                    )}
                                    <Badge variant={user.is_active ? "default" : "secondary"}>
                                        {user.is_active ? "فعال" : "غیرفعال"}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <CardDescription>اطلاعات هویتی کاربر</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <InfoRow label="نام" value={displayName} />
                        {user.employee?.personnel_code && (
                            <InfoRow
                                label="کد پرسنلی"
                                value={<span dir="ltr">{user.employee.personnel_code}</span>}
                            />
                        )}
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
                            <IconMasksTheater className="size-5" />
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

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconShieldCheck className="size-5" />
                        مجوزهای مؤثر
                    </CardTitle>
                    <CardDescription>
                        دسترسی‌های نهایی نقش فعال کاربر
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <EffectivePermissionsView userId={user.id} />
                </CardContent>
            </Card>

            <ResponsiveDialog
                open={rolesOpen}
                onOpenChange={setRolesOpen}
                title="مدیریت نقش کاربر"
                description={`تخصیص و مدیریت نقش‌های ${displayName}`}
            >
                <UserRoleManager
                    userId={user.id}
                    onRolesChanged={() => refetch()}
                />
            </ResponsiveDialog>
        </PageLayout>
    );
}
