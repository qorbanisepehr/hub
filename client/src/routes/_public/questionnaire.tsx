import { createRoute, Outlet } from "@tanstack/react-router";
import { Route as PublicRoute } from "@/routes/_public";

export const Route = createRoute({
    getParentRoute: () => PublicRoute,
    path: "/questionnaire",
    component: () => <Outlet />,
});
