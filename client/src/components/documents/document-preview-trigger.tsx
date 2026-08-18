import { cn } from "@/lib/utils";

type DocumentPreviewTriggerProps = {
    onClick: () => void;
    ariaLabel: string;
    className?: string;
    children: React.ReactNode;
};

/**
 * Accessible clickable wrapper for opening document previews.
 * Supports keyboard navigation (Enter/Space).
 */
export function DocumentPreviewTrigger({
    onClick,
    ariaLabel,
    className,
    children,
}: DocumentPreviewTriggerProps) {
    return (
        <div
            className={cn(
                "cursor-pointer rounded-md transition-colors hover:bg-muted/40",
                className,
            )}
            onClick={onClick}
            role="button"
            tabIndex={0}
            aria-label={ariaLabel}
            onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    onClick();
                }
            }}
        >
            {children}
        </div>
    );
}
