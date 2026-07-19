import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { UserCreatePage } from "@/features/rbac/pages/user-create-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users/create",
    beforeLoad: requirePermission(PERMISSIONS.USER_CREATE),
    component: UserCreatePage,
});
