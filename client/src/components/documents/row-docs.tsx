import type { ReactNode } from "react";

import { Badge } from "@/components/ui/badge";
import type { MissingRowDoc } from "@/features/documents/docs-feedback";

/**
 * Compact "incomplete documents" badge for a repeater row header,
 * e.g. «مدارک ناقص: شناسنامه، کارت ملی». Renders nothing once the
 * row meets every required page count.
 */
export function MissingDocsBadge({ missing }: { missing: MissingRowDoc[] }) {
    if (missing.length === 0) return null;

    return (
        <Badge variant="destructive">
            {`مدارک ناقص: ${missing.map(({ label }) => label).join("، ")}`}
        </Badge>
    );
}

type RowDocsPanelProps = {
    title: string;
    isLoading?: boolean;
    missing: MissingRowDoc[];
    /** Upload-field grid columns on desktop; defaults to 2. */
    columns?: 1 | 2;
    children: ReactNode;
};

/**
 * Shared "documents of this row" panel: bordered box with the section's
 * row title, a server-parity incomplete-pages message, and the category
 * upload fields as children. Used by every per-row document placement.
 */
export function RowDocsPanel({
    title,
    isLoading = false,
    missing,
    columns = 2,
    children,
}: RowDocsPanelProps) {
    return (
        <div className="rounded-lg border bg-muted/30 p-4">
            <p className="mb-3 text-sm font-medium text-muted-foreground">
                {title}
            </p>
            {!isLoading && missing.length > 0 && (
                <p className="mb-3 text-xs font-medium text-destructive">
                    مدارک ناقص:{" "}
                    {missing
                        .map(
                            ({ label, count, min }) =>
                                `${label} (${count} از ${min})`,
                        )
                        .join("، ")}
                </p>
            )}
            <div
                className={
                    columns === 1
                        ? "grid grid-cols-1"
                        : "grid grid-cols-1 gap-6 md:grid-cols-2"
                }
            >
                {children}
            </div>
        </div>
    );
}
