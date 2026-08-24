import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const AuditLogDetailPage = lazy(() =>
    import("@/features/audit/pages/audit-log-detail-page").then((m) => ({ default: m.AuditLogDetailPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/audit/$logId",
    beforeLoad: requirePermission(PERMISSIONS.AUDIT_VIEW),
    component: () => (
        <LazyRoute component={AuditLogDetailPage} fallback={<RouteLoadingFallback />} />
    ),
});
