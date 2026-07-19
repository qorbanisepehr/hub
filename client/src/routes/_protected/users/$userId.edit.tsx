import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { UserEditPage } from "@/features/rbac/pages/user-edit-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users/$userId/edit",
    beforeLoad: requirePermission(PERMISSIONS.USER_UPDATE),
    component: UserEditPage,
});
