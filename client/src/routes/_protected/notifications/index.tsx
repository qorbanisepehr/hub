import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const NotificationsPage = lazy(() =>
    import("@/features/notifications/pages/notifications-page").then((m) => ({ default: m.NotificationsPage })),
);

// Self-service area (architecture §122): every authenticated user has
// notifications — no permission guard, mirroring the backend routes.
const notificationsSearchSchema = paginatedSearchSchema({
    unread: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/notifications",
    validateSearch: notificationsSearchSchema,
    component: () => (
        <LazyRoute component={NotificationsPage} fallback={<RouteLoadingFallback />} />
    ),
});