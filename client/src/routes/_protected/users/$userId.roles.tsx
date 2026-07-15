import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { UserRolesPage } from "@/features/rbac/pages/user-roles-page";
import { requirePermission } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users/$userId/roles",
    beforeLoad: requirePermission("user.assign-roles"),
    component: UserRolesPage,
});
