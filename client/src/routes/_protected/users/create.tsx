import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const UserCreatePage = lazy(() =>
    import("@/features/rbac/pages/user-create-page").then((m) => ({ default: m.UserCreatePage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users/create",
    beforeLoad: requirePermission(PERMISSIONS.USER_CREATE),
    component: () => (
        <LazyRoute component={UserCreatePage} fallback={<RouteLoadingFallback />} />
    ),
});
