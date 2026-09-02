import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const ProfilePage = lazy(() =>
    import("@/features/auth/pages/profile-page").then((m) => ({ default: m.ProfilePage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/profile",
    component: () => (
        <LazyRoute component={ProfilePage} fallback={<RouteLoadingFallback />} />
    ),
});
