import { createRoute } from "@tanstack/react-router";
import { Route as QuestionnaireRoute } from "@/routes/_public/questionnaire";
import { QuestionnaireStartPage } from "@/features/questionnaire/pages/questionnaire-start-page";

export const Route = createRoute({
    getParentRoute: () => QuestionnaireRoute,
    path: "/",
    component: QuestionnaireStartPage,
});
