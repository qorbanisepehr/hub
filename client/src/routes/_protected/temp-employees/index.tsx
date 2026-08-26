import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";

const TempEmployeesPage = lazy(() =>
    import("@/features/temp-employees/pages/temp-employees-page").then((m) => ({
        default: m.TempEmployeesPage,
    })),
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/docs",
    component: () => (
        <LazyRoute
            component={TempEmployeesPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
