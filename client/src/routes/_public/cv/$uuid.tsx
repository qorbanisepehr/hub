import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as CvRoute } from "@/routes/_public/cv";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const CvFormPage = lazy(() =>
    import("@/features/cv/pages/cv-form-page").then((m) => ({ default: m.CvFormPage }))
);

export const Route = createRoute({
    getParentRoute: () => CvRoute,
    path: "$uuid",
    component: () => (
        <LazyRoute component={CvFormPage} fallback={<RouteLoadingFallback />} />
    ),
});
