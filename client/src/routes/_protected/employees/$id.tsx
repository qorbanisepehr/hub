import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const EmployeeViewPage = lazy(() =>
    import("@/features/employees/pages/employee-view-page").then((m) => ({ default: m.EmployeeViewPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees/$id",
    beforeLoad: requirePermission(PERMISSIONS.EMPLOYEE_VIEW),
    component: () => (
        <LazyRoute component={EmployeeViewPage} fallback={<RouteLoadingFallback />} />
    ),
});
