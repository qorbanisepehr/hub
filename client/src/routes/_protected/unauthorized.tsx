import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const UnauthorizedPage = lazy(() =>
    import("@/features/rbac/pages/unauthorized-page").then((m) => ({ default: m.UnauthorizedPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/unauthorized",
    component: () => (
        <LazyRoute component={UnauthorizedPage} fallback={<RouteLoadingFallback />} />
    ),
});
