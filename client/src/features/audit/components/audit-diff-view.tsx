import { Fragment } from "react";
import { IconArrowLeft } from "@tabler/icons-react";

import { cn, } from "@/lib/utils";
import { toPersianDate } from "@/lib/date-format";
import { useOptionLabelLookup } from "@/components/section-views/use-option-label";

type AuditDiffViewProps = {
    old?: Record<string, unknown> | null;
    new?: Record<string, unknown> | null;
    className?: string;
};

/** Humanize a snake_case attribute key for display. */
function humanizeKey(key: string): string {
    return key.replaceAll("_", " ");
}

const ISO_DATE = /^\d{4}-\d{2}-\d{2}(T| )?[\d:.]+/;

/**
 * Render one audit value for humans: dictionary slugs resolve through the
 * shared option map (city composites included via the same map's province
 * join is intentionally left to dedicated views), dates render in jalali,
 * booleans as بله/خیر, and anything unknown falls back to its raw form so
 * nothing silently disappears.
 */
function AuditValue({ value }: { value: unknown }) {
    const lookup = useOptionLabelLookup();

    if (value === null || value === undefined || value === "") {
        return <span className="text-muted-foreground">—</span>;
    }

    if (typeof value === "boolean") {
        return <span>{value ? "بله" : "خیر"}</span>;
    }

    if (typeof value === "string") {
        const label = lookup(value);

        if (label !== null) {
            return (
                <span title={value}>
                    {label}
                    <span className="ms-1 text-[10px] text-muted-foreground/60 font-mono">
                        {value}
                    </span>
                </span>
            );
        }

        if (ISO_DATE.test(value)) {
            return <span>{toPersianDate(value)}</span>;
        }

        return <span>{value}</span>;
    }

    return (
        <span className="font-mono text-xs break-all">
            {JSON.stringify(value)}
        </span>
    );
}

/**
 * Readable old → new diff rows for an audit log entry. Replaces raw
 * JSON dumps: every changed attribute renders as one row with the
 * humanized key, the previous value, and the new value.
 */
export function AuditDiffView({ old, new: newValues, className }: AuditDiffViewProps) {
    const keys = Array.from(
        new Set([...Object.keys(old ?? {}), ...Object.keys(newValues ?? {})]),
    ).sort();

    if (keys.length === 0) {
        return (
            <p className={cn("text-sm text-muted-foreground", className)}>
                تغییری ثبت نشده است.
            </p>
        );
    }

    return (
        <div className={cn("divide-y rounded-lg border", className)}>
            {keys.map((key) => {
                const before = old?.[key];
                const after = newValues?.[key];
                const unchanged = JSON.stringify(before) === JSON.stringify(after);

                return (
                    <div key={key} className="px-3 py-2 space-y-1">
                        <p
                            className="text-xs font-medium text-muted-foreground capitalize"
                            title={key}
                        >
                            {humanizeKey(key)}
                        </p>
                        <div className="flex items-start gap-2 text-sm">
                            {unchanged ? (
                                <AuditValue value={after} />
                            ) : (
                                <Fragment>
                                    <span className="text-destructive line-through decoration-destructive/40">
                                        <AuditValue value={before} />
                                    </span>
                                    <IconArrowLeft className="size-3.5 mt-1 shrink-0 text-muted-foreground" />
                                    <span>
                                        <AuditValue value={after} />
                                    </span>
                                </Fragment>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
