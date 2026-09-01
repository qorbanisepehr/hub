import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const UsersPage = lazy(() =>
    import("@/features/rbac/pages/users-page").then((m) => ({ default: m.UsersPage }))
);

const usersSearchSchema = paginatedSearchSchema({
    role: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users",
    validateSearch: usersSearchSchema,
    beforeLoad: requirePermission(PERMISSIONS.USER_VIEW),
    component: () => (
        <LazyRoute component={UsersPage} fallback={<RouteLoadingFallback />} />
    ),
});
