import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeeViewPage } from "@/features/employees/pages/employee-view-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/$id",
    beforeLoad: requirePermission(PERMISSIONS.EMPLOYEE_VIEW),
    component: EmployeeViewPage,
});
