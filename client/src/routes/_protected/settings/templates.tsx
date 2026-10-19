import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import {
    LazyRoute,
    RouteLoadingFallback,
} from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const TemplateSettingsSection = lazy(() =>
    import("@/features/board-meetings/pages/word-template-settings-section").then(
        (m) => ({ default: m.WordTemplateSettingsSection }),
    ),
);

export const Route = createRoute({
    getParentRoute: () => SettingsRoute,
    path: "templates",
    beforeLoad: requirePermission([
        PERMISSIONS.EXPORT_TEMPLATE_VIEW,
        PERMISSIONS.EXPORT_TEMPLATE_MANAGE,
    ]),
    component: () => (
        <LazyRoute
            component={TemplateSettingsSection}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
