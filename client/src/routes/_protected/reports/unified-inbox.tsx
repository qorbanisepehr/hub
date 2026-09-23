import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const UnifiedInboxPage = lazy(() =>
    import("@/features/reporting/pages/unified-inbox-page").then((m) => ({
        default: m.UnifiedInboxPage,
    })),
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/reports/unified-inbox",
    beforeLoad: requirePermission(PERMISSIONS.REPORT_UNIFIED_INBOX_VIEW),
    component: () => (
        <LazyRoute
            component={UnifiedInboxPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
