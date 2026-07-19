import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { PermissionsPage } from "@/features/rbac/pages/permissions-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/settings",
    beforeLoad: requirePermission([PERMISSIONS.DOCUMENT_CATEGORY_VIEW, PERMISSIONS.DOCUMENT_CATEGORY_MANAGE]),
    component: PermissionsPage,
});
