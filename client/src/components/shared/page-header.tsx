import type { ReactNode } from "react";
import { BackButton } from "@/components/shared/back-button";

export function PageHeader({
    title,
    description,
    backTo,
    children,
}: {
    title: string;
    description?: string;
    backTo?: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
                {backTo && <BackButton to={backTo} />}
                <div>
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
            {children}
        </div>
    );
}
