import { createRoute } from "@tanstack/react-router";
import { Route as QuestionnaireRoute } from "@/routes/_public/questionnaire";
import { QuestionnaireFormPage } from "@/features/recruitment/pages/questionnaire-form-page";

export const Route = createRoute({
    getParentRoute: () => QuestionnaireRoute,
    path: "$uuid",
    component: QuestionnaireFormPage,
});
