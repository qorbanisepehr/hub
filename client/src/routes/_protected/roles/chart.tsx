import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const RoleChartPage = lazy(() =>
    import("@/features/rbac/pages/role-chart-page").then((m) => ({ default: m.RoleChartPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles/chart",
    beforeLoad: requirePermission(PERMISSIONS.ROLE_VIEW),
    component: () => (
        <LazyRoute component={RoleChartPage} fallback={<RouteLoadingFallback />} />
    ),
});
