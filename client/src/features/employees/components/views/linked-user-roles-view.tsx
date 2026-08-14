import { useQuery } from "@tanstack/react-query";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { usePermission } from "@/features/auth/components/permission-guard";
import { fetchUserRoles } from "@/features/rbac/api";
import { userKeys } from "@/lib/query-keys";
import { PERMISSIONS } from "@/lib/permissions";
import type { Employee } from "@/features/employees/types";

export function LinkedUserRolesView({
    user,
}: {
    user: NonNullable<Employee["user"]>;
}) {
    const canViewRoles = usePermission([PERMISSIONS.ROLE_VIEW]);

    const { data: userRoles, isLoading } = useQuery({
        queryKey: userKeys.roles(user.id),
        queryFn: async () => {
            const { data } = await fetchUserRoles(user.id);
            return data;
        },
        enabled: canViewRoles,
    });

    if (!canViewRoles) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>نقش‌های کاربر</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        برای مشاهده نقش‌های کاربر دسترسی کافی ندارید
                    </p>
                </CardContent>
            </Card>
        );
    }

    const roles = userRoles?.roles ?? [];
    const activeRoleId = userRoles?.active_role?.id;

    return (
        <Card>
            <CardHeader>
                <CardTitle>نقش‌های کاربر</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {isLoading ? (
                    <div className="space-y-2">
                        <Skeleton className="h-8 w-40" />
                        <Skeleton className="h-8 w-32" />
                    </div>
                ) : roles.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        هیچ نقشی تخصیص داده نشده است
                    </p>
                ) : (
                    roles.map((role) => (
                        <div
                            key={role.id}
                            className="flex items-center justify-between gap-4"
                        >
                            <span className="text-sm font-medium">
                                {role.display_name}
                            </span>
                            {activeRoleId === role.id && (
                                <Badge variant="default">نقش فعال</Badge>
                            )}
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
