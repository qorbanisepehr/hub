import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { UnauthorizedPage } from "@/features/rbac/pages/unauthorized-page";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/unauthorized",
    component: UnauthorizedPage,
});
