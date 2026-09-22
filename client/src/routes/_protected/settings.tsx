import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const SettingsPage = lazy(() =>
    import("@/features/settings/pages/settings-page").then((m) => ({ default: m.SettingsPage }))
);

const settingsSearchSchema = z.object({
    tab: z
        .enum(["branding", "permissions", "form-options", "imports"])
        .optional(),
    /** Form-options admin list state (read on the form-options tab). */
    page: z.number().optional(),
    per_page: z.number().optional(),
    filter: z.string().optional(),
    group: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/settings",
    validateSearch: settingsSearchSchema,
    beforeLoad: requirePermission([
        PERMISSIONS.BRANDING_VIEW,
        PERMISSIONS.BRANDING_MANAGE,
        PERMISSIONS.DOCUMENT_CATEGORY_VIEW,
        PERMISSIONS.DOCUMENT_CATEGORY_MANAGE,
        PERMISSIONS.FORM_OPTIONS_VIEW,
        PERMISSIONS.FORM_OPTIONS_MANAGE,
        PERMISSIONS.EMPLOYEE_IMPORT,
    ]),
    component: () => (
        <LazyRoute component={SettingsPage} fallback={<RouteLoadingFallback />} />
    ),
});
