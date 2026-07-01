import { createRouter } from "@tanstack/react-router";
import { Route as RootRoute } from "@/routes/__root";
import { Route as PublicRoute } from "@/routes/_public";
import { Route as PublicIndexRoute } from "@/routes/_public/index";
import { Route as LoginRoute } from "@/routes/login";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { Route as DashboardRoute } from "@/routes/_protected/dashboard";

const routeTree = RootRoute.addChildren([
    PublicRoute.addChildren([PublicIndexRoute]),
    LoginRoute,
    ProtectedRoute.addChildren([DashboardRoute]),
]);

export const router = createRouter({ routeTree });

declare module "@tanstack/react-router" {
    interface Register {
        router: typeof router;
    }
}
