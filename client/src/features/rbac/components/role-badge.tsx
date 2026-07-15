import { Badge } from "@/components/ui/badge";
import type { Role } from "@/features/rbac/types";

interface RoleBadgeProps {
    role: Role;
    active?: boolean;
    showActiveLabel?: boolean;
}

export function RoleBadge({ role, active = false, showActiveLabel = false }: RoleBadgeProps) {
    return (
        <Badge variant={active ? "default" : "outline"}>
            {role.display_name}
            {showActiveLabel && active && (
                <span className="mr-1 text-xs opacity-70">(فعال)</span>
            )}
        </Badge>
    );
}
