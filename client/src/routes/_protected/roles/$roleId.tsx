import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { RoleEditPage } from "@/features/rbac/pages/role-edit-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles/$roleId",
    beforeLoad: requirePermission(PERMISSIONS.ROLE_UPDATE),
    component: RoleEditPage,
});
