import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeesPage } from "@/features/employees/pages/employees-page";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees",
    component: EmployeesPage,
});
