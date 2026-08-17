import type { ReactNode } from "react";
import type { ElementType } from "react";

export function EmptyState({
    icon: Icon,
    message,
    children,
}: {
    icon: ElementType;
    message: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
            <Icon className="size-12 mb-4 opacity-30" />
            <p>{message}</p>
            {children}
        </div>
    );
}
