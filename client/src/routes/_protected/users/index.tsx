import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { UsersPage } from "@/features/rbac/pages/users-page";
import { requirePermission } from "@/features/auth/guards";

const usersSearchSchema = z.object({
    page: z.number().optional(),
    per_page: z.number().optional(),
    sort: z.string().optional(),
    order: z.enum(["asc", "desc"]).optional(),
    filter: z.string().optional(),
    role: z.number().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/users",
    validateSearch: usersSearchSchema,
    beforeLoad: requirePermission("user.view"),
    component: UsersPage,
});
