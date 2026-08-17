import type { ReactNode } from "react";
import type { Icon } from "@tabler/icons-react";
import { IconAlertTriangle } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";

interface ErrorSectionProps {
    icon?: Icon;
    title?: string;
    description?: string;
    retryLabel?: string;
    onRetry?: () => void;
    children?: ReactNode;
}

export function ErrorSection({
    icon: Icon = IconAlertTriangle,
    title = "خطا در بارگذاری اطلاعات",
    description,
    retryLabel = "تلاش مجدد",
    onRetry,
    children,
}: ErrorSectionProps) {
    return (
        <div className="flex flex-col items-center justify-center py-12 text-center">
            <Icon className="size-12 mb-4 text-destructive/60" />
            <p className="text-sm font-medium text-foreground">{title}</p>
            {description && (
                <p className="mt-1 text-xs text-muted-foreground">
                    {description}
                </p>
            )}
            <div className="mt-4 flex items-center gap-2">
                {onRetry && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onRetry}
                    >
                        {retryLabel}
                    </Button>
                )}
                {children}
            </div>
        </div>
    );
}
