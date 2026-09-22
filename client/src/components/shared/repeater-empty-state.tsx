import type { ElementType } from "react";

import { cn } from "@/lib/utils";

/**
 * The approved empty-state style for in-form repeaters and compact editable
 * lists (D2 in the UI refactor plan). Distinct from the page-level
 * {@link EmptyState}: one dashed box, small text, no icon hero — the empty
 * message sits directly under the list's add-action so the next step is
 * obvious.
 */
export function RepeaterEmptyState({
    icon: Icon,
    message,
    className,
}: {
    icon?: ElementType;
    message: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                "flex items-center justify-center gap-2 rounded-lg border border-dashed px-3 py-6 text-center text-sm text-muted-foreground",
                className,
            )}
        >
            {Icon && <Icon className="size-4 shrink-0 opacity-50" />}
            <p>{message}</p>
        </div>
    );
}
