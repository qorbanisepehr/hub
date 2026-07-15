import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeeCreatePage } from "@/features/employees/pages/employee-create-page";
import { requirePermission } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/create",
    beforeLoad: requirePermission("employee.create"),
    component: EmployeeCreatePage,
});
