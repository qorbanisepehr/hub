import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const AuditLogsPage = lazy(() =>
    import("@/features/audit/pages/audit-logs-page").then((m) => ({ default: m.AuditLogsPage }))
);

const auditLogsSearchSchema = z.object({
    page: z.number().optional(),
    per_page: z.number().optional(),
    sort: z.string().optional(),
    order: z.enum(["asc", "desc"]).optional(),
    filter: z.string().optional(),
    category: z.string().optional(),
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
