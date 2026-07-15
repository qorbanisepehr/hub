import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeeViewPage } from "@/features/employees/pages/employee-view-page";
import { requirePermission } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/$id",
    beforeLoad: requirePermission(["employee.view_own", "employee.view_all"]),
    component: EmployeeViewPage,
});
