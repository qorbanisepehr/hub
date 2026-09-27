import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const EmployeesPage = lazy(() =>
    import("@/features/employees/pages/employees-page").then((m) => ({ default: m.EmployeesPage }))
);

const employeesSearchSchema = paginatedSearchSchema({
    status: z.string().optional(),
    status_not: z.string().optional(),
    employment_type: z.string().optional(),
    gender: z.string().optional(),
    hire_date_from: z.string().optional(),
    hire_date_to: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/employees",
    validateSearch: employeesSearchSchema,
    beforeLoad: requirePermission(PERMISSIONS.EMPLOYEE_LIST),
    component: () => (
        <LazyRoute component={EmployeesPage} fallback={<RouteLoadingFallback />} />
    ),
});
