import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const DashboardPage = lazy(() =>
    import("@/features/dashboard/pages/dashboard-page").then((m) => ({ default: m.DashboardPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/dashboard",
    component: () => (
        <LazyRoute component={DashboardPage} fallback={<RouteLoadingFallback />} />
    ),
});
