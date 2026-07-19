import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { RoleCreatePage } from "@/features/rbac/pages/role-create-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles/create",
    beforeLoad: requirePermission(PERMISSIONS.ROLE_CREATE),
    component: RoleCreatePage,
});
