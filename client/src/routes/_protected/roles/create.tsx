import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const RoleCreatePage = lazy(() =>
    import("@/features/rbac/pages/role-create-page").then((m) => ({ default: m.RoleCreatePage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles/create",
    beforeLoad: requirePermission(PERMISSIONS.ROLE_CREATE),
    component: () => (
        <LazyRoute component={RoleCreatePage} fallback={<RouteLoadingFallback />} />
    ),
});
