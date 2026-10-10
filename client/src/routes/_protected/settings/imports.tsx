import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const ImportSection = lazy(() =>
    import("@/features/imports/import-section").then((m) => ({ default: m.ImportSection }))
);

const DocumentBundleImport = lazy(() =>
    import("@/features/imports/document-bundle-import").then((m) => ({
        default: m.DocumentBundleImport,
    }))
);

export const Route = createRoute({
    getParentRoute: () => SettingsRoute,
    path: "imports",
    beforeLoad: requirePermission(PERMISSIONS.EMPLOYEE_IMPORT),
    component: () => (
        <div className="space-y-6">
            <LazyRoute component={ImportSection} fallback={<RouteLoadingFallback />} />
            <LazyRoute component={DocumentBundleImport} fallback={<RouteLoadingFallback />} />
        </div>
    ),
});