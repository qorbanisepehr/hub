import type { ReactNode } from "react";
import type { ElementType } from "react";

import { cn } from "@/lib/utils";

/**
 * The single empty-state component. Two densities:
 * - default (page/section level): py-12, icon size-12
 * - compact (inside cards, dialogs, repeaters, table cells): py-8, icon size-8
 */
export function EmptyState({
    icon: Icon,
    message,
    variant = "default",
    children,
    className,
}: {
    icon: ElementType;
    message: string;
    /** Use "compact" inside dialogs, cards and repeater tables. */
    variant?: "default" | "compact";
    children?: ReactNode;
    className?: string;
}) {
    const compact = variant === "compact";
    return (
        <div
            className={cn(
                "flex flex-col items-center justify-center text-muted-foreground",
                compact ? "py-8" : "py-12",
                className,
            )}
        >
            <Icon
                className={cn(
                    "mb-3 opacity-30",
                    compact ? "size-8" : "size-12",
                )}
            />
            <p className={cn(compact ? "text-sm" : "text-sm")}>{message}</p>
            {children}
        </div>
    );
}
