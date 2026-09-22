import type { ReactNode } from "react";
import {
    PageHeaderActions,
    type PageHeaderAction,
} from "./page-header-actions";

/**
 * The single list-page header (D7): page title, description and the primary
 * action, laid out exactly like DataTablePage's header slot. Mobile-safe via
 * flex-wrap / the "…" overflow menu.
 */
export function ListPageHeader({
    title,
    description,
    action,
    actions,
}: {
    title: string;
    description?: string;
    /** Primary action (legacy/custom) — usually the "create" button (already permission-gated by the caller). */
    action?: ReactNode;
    /** Structured actions that condense into a "…" menu on mobile. */
    actions?: PageHeaderAction[];
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
            {actions ? (
                <PageHeaderActions actions={actions} />
            ) : (
                action && (
                    <div className="flex flex-wrap items-center gap-2">{action}</div>
                )
            )}
        </>
    );
}
