import { useQuery } from "@tanstack/react-query";
import { isAxiosError } from "axios";
import { IconShieldOff, IconMasksTheater } from "@tabler/icons-react";
import { Badge } from "@/components/ui/badge";
import { EmptyState, ErrorSection, ViewSkeleton } from "@/components/layout";
import { fetchUserAuthorization } from "@/features/rbac/api";
import { userKeys } from "@/lib/query-keys";
import type { AuthorizationResponse } from "@/features/auth/types";

type PermissionGroups = Record<string, { allowed: string[]; denied: string[] }>;

function groupPermissions(
    permissions: AuthorizationResponse["permissions"],
): PermissionGroups {
    const groups: PermissionGroups = {};

    for (const [name, { allowed }] of Object.entries(permissions)) {
        const lastDot = name.lastIndexOf(".");
        const group = lastDot === -1 ? name : name.slice(0, lastDot);
        const action = lastDot === -1 ? name : name.slice(lastDot + 1);

        groups[group] ??= { allowed: [], denied: [] };
        (allowed ? groups[group].allowed : groups[group].denied).push(action);
    }

    return groups;
}

interface EffectivePermissionsViewProps {
    userId: number;
}

export function EffectivePermissionsView({ userId }: EffectivePermissionsViewProps) {
    const {
        data: authorization,
        isLoading,
        isError,
        error,
        refetch,
    } = useQuery({
        queryKey: userKeys.authorization(userId),
        queryFn: async () => {
            const res = await fetchUserAuthorization(userId);
            return res.data.data;
        },
    });

    if (isLoading) {
        return <ViewSkeleton leftRows={3} />;
    }

    if (isError) {
        const message = isAxiosError(error)
            ? error.response?.status === 403
                ? "دسترسی به اطلاعات مجوزها غیرمجاز است"
                : "خطا در دریافت مجوزهای مؤثر"
            : "خطا در دریافت مجوزهای مؤثر";

        return <ErrorSection description={message} onRetry={() => refetch()} />;
    }

    if (!authorization) {
        return (
            <EmptyState
                icon={IconShieldOff}
                message="امکان دریافت مجوزهای مؤثر وجود ندارد"
                variant="compact"
            />
        );
    }

    if (authorization.role === null) {
        return (
            <EmptyState
                icon={IconMasksTheater}
                message="هیچ نقشی تخصیص داده نشده است"
                variant="compact"
            />
        );
    }

    const groups = groupPermissions(authorization.permissions);
    const hasAny = Object.values(groups).some((g) => g.allowed.length > 0);

    if (!hasAny) {
        return (
            <EmptyState
                icon={IconShieldOff}
                message={`هیچ دسترسی فعالی برای نقش ${authorization.role.display_name} نیست`}
                variant="compact"
            />
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-2">
                <Badge>{authorization.role.display_name}</Badge>
                <span className="text-xs text-muted-foreground">
                    نقش فعال
                </span>
            </div>

            <div className="space-y-3">
                {Object.entries(groups).map(([group, { allowed, denied }]) => (
                    <div key={group}>
                        <h4 className="mb-1.5 text-xs font-medium text-muted-foreground" dir="ltr">
                            {group}
                        </h4>
                        <div className="flex flex-wrap gap-1.5">
                            {allowed.map((action) => (
                                <Badge key={action} variant="default" dir="ltr">
                                    {action}
                                </Badge>
                            ))}
                            {denied.map((action) => (
                                <Badge key={action} variant="outline" dir="ltr">
                                    {action}
                                </Badge>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
