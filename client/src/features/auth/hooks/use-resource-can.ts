import { useQuery } from "@tanstack/react-query";
import { useAuth } from "@/features/auth";
import { checkPermission } from "@/features/auth/api";

export function useResourceCan(
    permission: string,
    resourceType: string,
    resourceId: number | null,
): boolean {
    const { user } = useAuth();
    const isSuperAdmin = user?.is_super_admin ?? false;

    const { data } = useQuery({
        queryKey: ["authorization-check", permission, resourceType, resourceId],
        queryFn: async () => {
            const res = await checkPermission({
                permission,
                resource_type: resourceType,
                resource_id: resourceId ?? undefined,
            });

            return res.data.allowed;
        },
        enabled: !!user && resourceId !== null,
    });

    return isSuperAdmin || (data ?? false);
}
