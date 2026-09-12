import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const AuditLogsPage = lazy(() =>
    import("@/features/audit/pages/audit-logs-page").then((m) => ({ default: m.AuditLogsPage }))
);

const auditLogsSearchSchema = paginatedSearchSchema({
    category: z.string().optional(),
    category_not: z.string().optional(),
    event: z.string().optional(),
    event_not: z.string().optional(),
    date_from: z.string().optional(),
    date_to: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/audit",
    validateSearch: auditLogsSearchSchema,
    beforeLoad: requirePermission(PERMISSIONS.AUDIT_VIEW),
    component: () => (
        <LazyRoute component={AuditLogsPage} fallback={<RouteLoadingFallback />} />
    ),
});
