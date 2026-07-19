import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { UserViewPage } from "@/features/rbac/pages/user-view-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users/$userId",
    beforeLoad: requirePermission(PERMISSIONS.USER_VIEW),
    component: UserViewPage,
});
