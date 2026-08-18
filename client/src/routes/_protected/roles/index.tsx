import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const RolesPage = lazy(() =>
    import("@/features/rbac/pages/roles-page").then((m) => ({ default: m.RolesPage }))
);

const rolesSearchSchema = z.object({
    page: z.number().optional(),
    per_page: z.number().optional(),
    sort: z.string().optional(),
    order: z.enum(["asc", "desc"]).optional(),
    filter: z.string().optional(),
    is_active: z.boolean().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles",
    validateSearch: rolesSearchSchema,
    beforeLoad: requirePermission(PERMISSIONS.ROLE_VIEW),
    component: () => (
        <LazyRoute component={RolesPage} fallback={<RouteLoadingFallback />} />
    ),
});
