import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import {
    LazyRoute,
    RouteLoadingFallback,
} from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";
import { z } from "zod";

const AiSettingsSection = lazy(() =>
    import("@/features/ai").then((m) => ({ default: m.AiSettingsSection })),
);

const aiSearchSchema = paginatedSearchSchema({
    ai_page: z.number().optional(),
    ai_per_page: z.number().optional(),
    ai_sort: z.string().optional(),
    ai_order: z.enum(["asc", "desc"]).optional(),
    ai_filter: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => SettingsRoute,
    path: "ai",
    validateSearch: aiSearchSchema,
    beforeLoad: requirePermission([
        PERMISSIONS.AI_PROVIDER_VIEW,
        PERMISSIONS.AI_PROVIDER_MANAGE,
    ]),
    component: () => (
        <LazyRoute
            component={AiSettingsSection}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
