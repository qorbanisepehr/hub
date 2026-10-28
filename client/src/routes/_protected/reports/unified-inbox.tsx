import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const UnifiedInboxPage = lazy(() =>
    import("@/features/reporting/pages/unified-inbox-page").then((m) => ({
        default: m.UnifiedInboxPage,
    })),
);

const unifiedInboxSearchSchema = paginatedSearchSchema({
    source: z.string().optional(),
    source_not: z.string().optional(),
    type: z.string().optional(),
    type_not: z.string().optional(),
    status: z.string().optional(),
    status_not: z.string().optional(),
    date_from: z.string().optional(),
    date_to: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/reports/unified-inbox",
    validateSearch: unifiedInboxSearchSchema,
    beforeLoad: requirePermission(PERMISSIONS.REPORT_UNIFIED_INBOX_VIEW),
    component: () => (
        <LazyRoute
            component={UnifiedInboxPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
