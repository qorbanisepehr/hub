import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const UserEditPage = lazy(() =>
    import("@/features/rbac/pages/user-edit-page").then((m) => ({ default: m.UserEditPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users/$userId/edit",
    beforeLoad: requirePermission(PERMISSIONS.USER_UPDATE),
    component: () => (
        <LazyRoute component={UserEditPage} fallback={<RouteLoadingFallback />} />
    ),
});
