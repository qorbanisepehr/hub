import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as QuestionnaireRoute } from "@/routes/_public/questionnaire";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const QuestionnaireStartPage = lazy(() =>
    import("@/features/questionnaire/pages/questionnaire-start-page").then((m) => ({ default: m.QuestionnaireStartPage }))
);

export const Route = createRoute({
    getParentRoute: () => QuestionnaireRoute,
    path: "/",
    component: () => (
        <LazyRoute component={QuestionnaireStartPage} fallback={<RouteLoadingFallback />} />
    ),
});
