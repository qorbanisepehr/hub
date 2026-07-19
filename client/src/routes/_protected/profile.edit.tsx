import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { ProfileEditPage } from "@/features/auth/pages/profile-edit-page";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/profile/edit",
    component: ProfileEditPage,
});
