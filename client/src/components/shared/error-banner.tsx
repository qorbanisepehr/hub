import { IconAlertTriangle } from "@tabler/icons-react";

interface ErrorBannerProps {
    message: string;
    onRetry?: () => void;
    retryLabel?: string;
}

export function ErrorBanner({
    message,
    onRetry,
    retryLabel = "تلاش مجدد",
}: ErrorBannerProps) {
    return (
        <div className="flex items-start gap-3 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
            <IconAlertTriangle className="mt-0.5 size-4 shrink-0" />
            <div className="flex-1">
                <p>{message}</p>
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
