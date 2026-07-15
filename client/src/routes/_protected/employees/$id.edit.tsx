import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeeEditPage } from "@/features/employees/pages/employee-edit-page";
import { requirePermission } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/$id/edit",
    beforeLoad: requirePermission(["employee.update_own", "employee.update_all"]),
    component: EmployeeEditPage,
});
