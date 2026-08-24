import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const AuditRetentionPage = lazy(() =>
    import("@/features/audit/pages/audit-retention-page").then((m) => ({ default: m.AuditRetentionPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/audit/retention",
    beforeLoad: requirePermission(PERMISSIONS.AUDIT_MANAGE),
    component: () => (
        <LazyRoute component={AuditRetentionPage} fallback={<RouteLoadingFallback />} />
    ),
});
