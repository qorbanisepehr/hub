import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const FormOptionsSection = lazy(() =>
    import("@/features/settings/pages/form-options-section").then((m) => ({ default: m.FormOptionsSection }))
);

const formOptionsSearchSchema = paginatedSearchSchema({
    group: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => SettingsRoute,
    path: "form-options",
    validateSearch: formOptionsSearchSchema,
    beforeLoad: requirePermission([PERMISSIONS.FORM_OPTIONS_VIEW, PERMISSIONS.FORM_OPTIONS_MANAGE]),
    component: () => (
        <LazyRoute component={FormOptionsSection} fallback={<RouteLoadingFallback />} />
    ),
});
