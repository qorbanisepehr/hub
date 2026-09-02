import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const ProfileEditPage = lazy(() =>
    import("@/features/auth/pages/profile-edit-page").then((m) => ({ default: m.ProfileEditPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/profile/edit",
    component: () => (
        <LazyRoute component={ProfileEditPage} fallback={<RouteLoadingFallback />} />
    ),
});
