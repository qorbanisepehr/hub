import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import {
    LazyRoute,
    RouteLoadingFallback,
} from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const BrandingSettingsSection = lazy(() =>
    import("@/features/settings/pages/branding-settings-page").then((m) => ({
        default: m.BrandingSettingsSection,
    })),
);

export const Route = createRoute({
    getParentRoute: () => SettingsRoute,
    path: "branding",
    beforeLoad: requirePermission([
        PERMISSIONS.BRANDING_VIEW,
        PERMISSIONS.BRANDING_MANAGE,
    ]),
    component: () => (
        <LazyRoute
            component={BrandingSettingsSection}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
