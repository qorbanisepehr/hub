import { Badge } from "@/components/ui/badge";

/**
 * The single boolean active/inactive badge (D8): default variant when
 * active, secondary when inactive — one place to restyle if the convention
 * ever changes.
 */
export function ActiveBadge({
    isActive,
    className,
}: {
    isActive: boolean | null | undefined;
    className?: string;
}) {
    const active = Boolean(isActive);
    return (
        <Badge variant={active ? "default" : "secondary"} className={className}>
            {active ? "فعال" : "غیرفعال"}
        </Badge>
    );
}
