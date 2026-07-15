import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { RolesPage } from "@/features/rbac/pages/roles-page";
import { requirePermission } from "@/features/auth/guards";

const rolesSearchSchema = z.object({
    page: z.number().optional(),
    per_page: z.number().optional(),
    sort: z.string().optional(),
    order: z.enum(["asc", "desc"]).optional(),
    filter: z.string().optional(),
    is_active: z.boolean().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/roles",
    validateSearch: rolesSearchSchema,
    beforeLoad: requirePermission("role.view"),
    component: RolesPage,
});
