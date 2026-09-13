import { cn } from "@/lib/utils";
import { IconAlertTriangle } from "@tabler/icons-react";

/**
 * The single inline-error banner (merged SubmitErrors/ErrorBanner contract).
 *
 * Renders one message or a bullet list of submit errors with the exact same
 * visual language:
 * - single message → plain `<p>`
 * - multiple errors → bullet list
 * - optional retry link (mutation-style banners)
 */
export function ErrorBanner({
    message,
    errors,
    onRetry,
    retryLabel = "تلاش مجدد",
    className,
}: {
    /** Single message (error banner usage). */
    message?: string;
    /** Multiple submit errors (wizard usage) — rendered as a list when > 1. */
    errors?: string[];
    onRetry?: () => void;
    retryLabel?: string;
    className?: string;
}) {
    const list = errors ?? (message ? [message] : []);
    if (list.length === 0) return null;

    return (
        <div
            className={cn(
                "flex items-start gap-3 rounded-lg bg-destructive/10 p-3 text-sm text-destructive",
                className,
            )}
        >
            <IconAlertTriangle className="mt-0.5 size-4 shrink-0" />
            <div className="flex-1">
                {list.length === 1 ? (
                    <p>{list[0]}</p>
                ) : (
                    <ul className="space-y-1 list-disc ms-4">
                        {list.map((err, i) => (
                            // oxlint-disable-next-line react/no-array-index-key -- static error list; strings may repeat
                            <li key={i}>{err}</li>
                        ))}
                    </ul>
                )}
                {onRetry && (
                    <button
                        type="button"
                        onClick={onRetry}
                        className="mt-1 text-xs font-medium underline underline-offset-2 hover:text-destructive/80"
                    >
                        {retryLabel}
                    </button>
                )}
            </div>
        </div>
    );
}
