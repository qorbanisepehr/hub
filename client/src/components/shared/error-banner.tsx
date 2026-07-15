interface ErrorBannerProps {
    message: string;
}

export function ErrorBanner({ message }: ErrorBannerProps) {
    return (
        <div className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
            {message}
        </div>
    );
}
