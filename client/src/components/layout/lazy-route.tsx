import { Suspense } from "react";
import type { ComponentType, LazyExoticComponent } from "react";

/**
 * Wraps a lazy-loaded component with a Suspense fallback.
 */
export function LazyRoute({
    component: Component,
    fallback = null,
}: {
    component: LazyExoticComponent<ComponentType>;
    fallback?: React.ReactNode;
}) {
    return (
        <Suspense fallback={fallback}>
            <Component />
        </Suspense>
    );
}

/**
 * Loading fallback for route transitions.
 */
export function RouteLoadingFallback() {
    return (
        <div className="flex h-full items-center justify-center">
            <div className="flex flex-col items-center gap-3">
                <div className="size-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                <span className="text-sm text-muted-foreground">در حال بارگذاری...</span>
            </div>
        </div>
    );
}
