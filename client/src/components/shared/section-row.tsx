import type { ReactNode } from "react";

type SectionRowProps = {
    label: string;
    value: unknown;
    variant?: "baseline" | "between" | "column";
    hideEmpty?: boolean;
};

function isEmptyValue(value: unknown): boolean {
    return value === null || value === undefined || value === "" || value === "-";
}

export function SectionRow({
    label,
    value,
    variant = "baseline",
    hideEmpty = false,
}: SectionRowProps) {
    if (hideEmpty && isEmptyValue(value)) {
        return null;
    }

    const node = value as ReactNode;

    if (variant === "between") {
        return (
            <div className="flex items-start justify-between gap-6 py-3">
                <span className="text-sm text-muted-foreground">{label}</span>
                <span className="text-sm text-end">{node}</span>
            </div>
        );
    }

    if (variant === "column") {
        return (
            <div className="flex flex-col gap-0.5">
                <span className="text-xs text-muted-foreground">{label}</span>
                <span className="text-sm">
                    {node || <span className="text-muted-foreground">—</span>}
                </span>
            </div>
        );
    }

    return (
        <div className="flex items-baseline gap-2 py-2 border-b last:border-b-0">
            <span className="text-sm text-muted-foreground min-w-32">
                {label}
            </span>
            <span className="text-sm font-medium">{node ?? "—"}</span>
        </div>
    );
}
