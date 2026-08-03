import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { RoleChartPage } from "@/features/rbac/pages/role-chart-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles/chart",
    beforeLoad: requirePermission(PERMISSIONS.ROLE_VIEW),
    component: RoleChartPage,
});
