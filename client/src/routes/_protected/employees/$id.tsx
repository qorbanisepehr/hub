import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { EmployeeViewPage } from "@/features/employees/pages/employee-view-page";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/$id",
    component: EmployeeViewPage,
});
