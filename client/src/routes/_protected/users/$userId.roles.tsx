import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const UserRolesPage = lazy(() =>
    import("@/features/rbac/pages/user-roles-page").then((m) => ({ default: m.UserRolesPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users/$userId/roles",
    beforeLoad: requirePermission(PERMISSIONS.USER_ASSIGN_ROLES),
    component: () => (
        <LazyRoute component={UserRolesPage} fallback={<RouteLoadingFallback />} />
    ),
});
