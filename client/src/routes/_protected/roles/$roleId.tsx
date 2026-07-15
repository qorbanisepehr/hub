import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { RoleEditPage } from "@/features/rbac/pages/role-edit-page";
import { requirePermission } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles/$roleId",
    beforeLoad: requirePermission("role.update"),
    component: RoleEditPage,
});
