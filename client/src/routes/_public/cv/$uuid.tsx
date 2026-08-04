import { createRoute } from "@tanstack/react-router";
import { Route as CvRoute } from "@/routes/_public/cv";
import { CvFormPage } from "@/features/cv/pages/cv-form-page";

export const Route = createRoute({
    getParentRoute: () => CvRoute,
    path: "$uuid",
    component: CvFormPage,
});
