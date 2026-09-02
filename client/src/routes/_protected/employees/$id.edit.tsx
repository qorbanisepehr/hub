import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const EmployeeEditPage = lazy(() =>
    import("@/features/employees/pages/employee-edit-page").then((m) => ({ default: m.EmployeeEditPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/$id/edit",
    beforeLoad: requirePermission(PERMISSIONS.EMPLOYEE_UPDATE),
    component: () => (
        <LazyRoute component={EmployeeEditPage} fallback={<RouteLoadingFallback />} />
    ),
});
