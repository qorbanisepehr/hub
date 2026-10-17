import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import {
    LazyRoute,
    RouteLoadingFallback,
} from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const SettingsPage = lazy(() =>
    import("@/features/settings/pages/settings-page").then((m) => ({
        default: m.SettingsPage,
    })),
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/settings",
    beforeLoad: requirePermission([
        PERMISSIONS.BRANDING_VIEW,
        PERMISSIONS.BRANDING_MANAGE,
        PERMISSIONS.DOCUMENT_CATEGORY_VIEW,
        PERMISSIONS.DOCUMENT_CATEGORY_MANAGE,
        PERMISSIONS.FORM_OPTIONS_VIEW,
        PERMISSIONS.FORM_OPTIONS_MANAGE,
        PERMISSIONS.EMPLOYEE_IMPORT,
        PERMISSIONS.TAG_VIEW,
        PERMISSIONS.TAG_MANAGE,
        PERMISSIONS.AI_PROVIDER_VIEW,
        PERMISSIONS.AI_PROVIDER_MANAGE,
        PERMISSIONS.EXPORT_TEMPLATE_VIEW,
        PERMISSIONS.EXPORT_TEMPLATE_MANAGE,
    ]),
    component: () => (
        <LazyRoute
            component={SettingsPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
