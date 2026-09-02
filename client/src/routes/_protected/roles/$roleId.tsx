import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const RoleEditPage = lazy(() =>
    import("@/features/rbac/pages/role-edit-page").then((m) => ({ default: m.RoleEditPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles/$roleId",
    beforeLoad: requirePermission(PERMISSIONS.ROLE_UPDATE),
    component: () => (
        <LazyRoute component={RoleEditPage} fallback={<RouteLoadingFallback />} />
    ),
});
