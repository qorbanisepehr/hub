import { createRoute } from "@tanstack/react-router";
import { Route as CvRoute } from "@/routes/_public/cv";
import { CvStartPage } from "@/features/cv/pages/cv-start-page";

export const Route = createRoute({
    getParentRoute: () => CvRoute,
    path: "/",
    component: CvStartPage,
});
