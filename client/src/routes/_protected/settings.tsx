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
        .enum(["branding", "permissions", "form-options", "imports", "tags", "ai"])
        .optional(),
    /** Form-options admin list state (read on the form-options tab). */
    page: z.number().optional(),
    per_page: z.number().optional(),
    filter: z.string().optional(),
    group: z.string().optional(),
    /**
     * The AI tab's second table (agents) keeps its own keys. Both tables
     * share this route's URL, so reusing `page`/`filter` would have the
     * providers list and the agents list page and filter each other.
     */
    ai_page: z.number().optional(),
    ai_per_page: z.number().optional(),
    ai_sort: z.string().optional(),
    ai_order: z.enum(["asc", "desc"]).optional(),
    ai_filter: z.string().optional(),
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
        PERMISSIONS.TAG_VIEW,
        PERMISSIONS.TAG_MANAGE,
        PERMISSIONS.AI_PROVIDER_VIEW,
        PERMISSIONS.AI_PROVIDER_MANAGE,
    ]),
    component: () => (
        <LazyRoute component={SettingsPage} fallback={<RouteLoadingFallback />} />
    ),
});
