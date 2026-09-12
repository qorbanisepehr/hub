import type { ReactNode } from "react";

/**
 * The single list-page header (D7): page title, description and the primary
 * action, laid out exactly like DataTablePage's header slot. Mobile-safe via
 * flex-wrap.
 */
export function ListPageHeader({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    /** Primary action — usually the "create" button (already permission-gated by the caller). */
    action?: ReactNode;
}) {
    return (
        <>
            <div className="min-w-0">
                <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                {description && (
                    <p className="text-sm text-muted-foreground mt-1">
                        {description}
                    </p>
                )}
            </div>
            {action && (
                <div className="flex flex-wrap items-center gap-2">{action}</div>
            )}
        </>
    );
}
