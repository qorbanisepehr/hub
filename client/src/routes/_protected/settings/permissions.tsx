import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const PermissionsSection = lazy(() =>
    import("@/features/rbac/pages/permissions-section").then((m) => ({ default: m.PermissionsSection }))
);

export const Route = createRoute({
    getParentRoute: () => SettingsRoute,
    path: "permissions",
    beforeLoad: requirePermission([PERMISSIONS.DOCUMENT_CATEGORY_VIEW, PERMISSIONS.DOCUMENT_CATEGORY_MANAGE]),
    component: () => (
        <LazyRoute component={PermissionsSection} fallback={<RouteLoadingFallback />} />
    ),
});
