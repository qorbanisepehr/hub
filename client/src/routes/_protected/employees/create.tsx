import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeeCreatePage } from "@/features/employees/pages/employee-create-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/create",
    beforeLoad: requirePermission(PERMISSIONS.EMPLOYEE_CREATE),
    component: EmployeeCreatePage,
});
