import { lazy, Suspense } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as RootRoute } from "@/routes/__root";
import { RouteLoadingFallback } from "@/components/layout/lazy-route";
import { redirectIfAuthenticated } from "@/features/auth/guards";

const LoginPage = lazy(() =>
    import("@/features/auth/pages/login-page").then((m) => ({ default: m.LoginPage }))
);

export const Route = createRoute({
    getParentRoute: () => RootRoute,
    path: "/login",
    validateSearch: z.object({
        redirect: z.string().optional(),
    }),
    beforeLoad: () => redirectIfAuthenticated(),
    component: function LoginRouteComponent() {
        const { redirect: redirectTo } = Route.useSearch();

        return (
            <Suspense fallback={<RouteLoadingFallback />}>
                <LoginPage redirectTo={redirectTo} />
            </Suspense>
        );
    },
});
