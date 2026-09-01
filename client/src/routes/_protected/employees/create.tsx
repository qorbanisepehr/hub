import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const EmployeeCreatePage = lazy(() =>
    import("@/features/employees/pages/employee-create-page").then((m) => ({ default: m.EmployeeCreatePage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/create",
    beforeLoad: requirePermission(PERMISSIONS.EMPLOYEE_CREATE),
    component: () => (
        <LazyRoute component={EmployeeCreatePage} fallback={<RouteLoadingFallback />} />
    ),
});
