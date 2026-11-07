import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";
import { listViewSchema } from "@/components/data-table";

const TagsSection = lazy(() =>
    import("@/features/tags/pages/tags-section").then((m) => ({ default: m.TagsSection }))
);

const tagsSearchSchema = paginatedSearchSchema({ view: listViewSchema });

export const Route = createRoute({
    getParentRoute: () => SettingsRoute,
    path: "tags",
    validateSearch: tagsSearchSchema,
    beforeLoad: requirePermission([PERMISSIONS.TAG_VIEW, PERMISSIONS.TAG_MANAGE]),
    component: () => (
        <LazyRoute component={TagsSection} fallback={<RouteLoadingFallback />} />
    ),
});
