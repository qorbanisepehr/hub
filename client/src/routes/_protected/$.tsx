import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { ErrorPage } from "@/components/layout";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "$",
    component: NotFound,
});

function NotFound() {
    return <ErrorPage status={404} homeTo="/dashboard" />;
}
