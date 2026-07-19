import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { ProfilePage } from "@/features/auth/pages/profile-page";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/profile",
    component: ProfilePage,
});
