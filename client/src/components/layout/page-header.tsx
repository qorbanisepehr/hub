import type { ReactNode } from "react";
import { BackButton } from "@/components/layout";
import {
    PageHeaderActions,
    type PageHeaderAction,
} from "./page-header-actions";

export function PageHeader({
    title,
    description,
    backTo,
    children,
    actions,
}: {
    title: string;
    description?: string;
    backTo?: string;
    /** Complex/custom action nodes (e.g. dialogs) — desktop only, hidden on mobile. */
    children?: ReactNode;
    /** Structured actions that condense into a "…" menu on mobile. */
    actions?: PageHeaderAction[];
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-y-3">
            <div className="flex min-w-0 items-center gap-3">
                {backTo && <BackButton to={backTo} />}
                <div className="min-w-0">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="text-sm text-muted-foreground mt-1">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
                {actions ? <PageHeaderActions actions={actions} /> : null}
                {children ? (
                    <div className="hidden flex-wrap items-center gap-2 md:flex">
                        {children}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
