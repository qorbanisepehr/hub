import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const AttendanceReportPage = lazy(() =>
    import("@/features/reporting/pages/attendance-report-page").then((m) => ({
        default: m.AttendanceReportPage,
    })),
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/reports/attendance",
    beforeLoad: requirePermission(PERMISSIONS.REPORT_ATTENDANCE_VIEW),
    component: () => (
        <LazyRoute
            component={AttendanceReportPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
