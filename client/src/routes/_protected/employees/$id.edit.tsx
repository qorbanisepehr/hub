import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeeEditPage } from "@/features/employees/pages/employee-edit-page";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/$id/edit",
    component: EmployeeEditPage,
});
