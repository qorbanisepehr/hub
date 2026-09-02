import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as PublicRoute } from "@/routes/_public";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const HomePage = lazy(() =>
    import("@/features/home/pages/home-page").then((m) => ({ default: m.HomePage }))
);

export const Route = createRoute({
    getParentRoute: () => PublicRoute,
    path: "/",
    component: () => (
        <LazyRoute component={HomePage} fallback={<RouteLoadingFallback />} />
    ),
});
