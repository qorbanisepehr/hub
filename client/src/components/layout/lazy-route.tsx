import { Suspense } from "react";
import type { ComponentType, LazyExoticComponent } from "react";
import { PageSkeleton } from "@/components/layout";

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
 *
 * Skeleton-based so page loads stay consistent (decision 2026-09-14: page
 * transitions show the top progress bar in TopLoader; content loading shows
 * skeletons. A centered spinner is only acceptable inside buttons).
 */
export function RouteLoadingFallback() {
    return <PageSkeleton />;
}
