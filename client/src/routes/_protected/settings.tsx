import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { SettingsPage } from "@/features/settings/pages/settings-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/settings",
    validateSearch: z.object({
        tab: z.enum(["branding", "permissions"]).optional(),
    }),
    beforeLoad: requirePermission([
        PERMISSIONS.BRANDING_VIEW,
        PERMISSIONS.BRANDING_MANAGE,
        PERMISSIONS.DOCUMENT_CATEGORY_VIEW,
        PERMISSIONS.DOCUMENT_CATEGORY_MANAGE,
    ]),
    component: SettingsPage,
});
