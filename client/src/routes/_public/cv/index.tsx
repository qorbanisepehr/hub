import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as CvRoute } from "@/routes/_public/cv";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const CvStartPage = lazy(() =>
    import("@/features/cv/pages/cv-start-page").then((m) => ({ default: m.CvStartPage }))
);

export const Route = createRoute({
    getParentRoute: () => CvRoute,
    path: "/",
    component: () => (
        <LazyRoute component={CvStartPage} fallback={<RouteLoadingFallback />} />
    ),
});
