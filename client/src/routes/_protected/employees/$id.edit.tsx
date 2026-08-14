import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeeEditPage } from "@/features/employees/pages/employee-edit-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/$id/edit",
    beforeLoad: requirePermission(PERMISSIONS.EMPLOYEE_UPDATE),
    component: EmployeeEditPage,
});
