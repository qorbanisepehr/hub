import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as RootRoute } from "@/routes/__root";
import { LoginPage } from "@/features/auth/pages/login-page";
import { redirectIfAuthenticated } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => RootRoute,
    path: "/login",
    validateSearch: z.object({
        redirect: z.string().optional(),
    }),
    beforeLoad: () => redirectIfAuthenticated(),
    component: function LoginRouteComponent() {
        const { redirect: redirectTo } = Route.useSearch();

        return <LoginPage redirectTo={redirectTo} />;
    },
});
