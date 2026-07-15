import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { PermissionsPage } from "@/features/rbac/pages/permissions-page";
import { requirePermission } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/settings",
    beforeLoad: requirePermission(["permission-category.view", "permission-category.manage"]),
    component: PermissionsPage,
});
