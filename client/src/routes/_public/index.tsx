import { createRoute } from "@tanstack/react-router";
import { Route as PublicRoute } from "@/routes/_public";
import { HomePage } from "@/features/home/pages/home-page";

export const Route = createRoute({
    getParentRoute: () => PublicRoute,
    path: "/",
    component: HomePage,
});
