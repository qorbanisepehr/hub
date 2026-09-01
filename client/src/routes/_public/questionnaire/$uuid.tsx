import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as QuestionnaireRoute } from "@/routes/_public/questionnaire";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const QuestionnaireFormPage = lazy(() =>
    import("@/features/questionnaire/pages/questionnaire-form-page").then((m) => ({ default: m.QuestionnaireFormPage }))
);

export const Route = createRoute({
    getParentRoute: () => QuestionnaireRoute,
    path: "$uuid",
    component: () => (
        <LazyRoute component={QuestionnaireFormPage} fallback={<RouteLoadingFallback />} />
    ),
});
