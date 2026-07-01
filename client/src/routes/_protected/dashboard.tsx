import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { DashboardPage } from "@/features/dashboard/pages/dashboard-page";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/dashboard",
    component: DashboardPage,
});
