import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";
import { listViewSchema } from "@/components/data-table";

const AttendanceReportPage = lazy(() =>
    import("@/features/reporting/pages/attendance-report-page").then((m) => ({
        default: m.AttendanceReportPage,
    })),
);

const attendanceSearchSchema = paginatedSearchSchema({
    date_from: z.string().optional(),
    date_to: z.string().optional(),
    view: listViewSchema,
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/reports/attendance",
    validateSearch: attendanceSearchSchema,
    beforeLoad: requirePermission(PERMISSIONS.REPORT_ATTENDANCE_VIEW),
    component: () => (
        <LazyRoute
            component={AttendanceReportPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
