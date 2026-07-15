import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { UserViewPage } from "@/features/rbac/pages/user-view-page";
import { requirePermission } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users/$userId",
    beforeLoad: requirePermission("user.view"),
    component: UserViewPage,
});
