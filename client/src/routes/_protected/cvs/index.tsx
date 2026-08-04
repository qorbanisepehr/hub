import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { CvsBankPage } from "@/features/cv/pages/cvs-bank-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const cvsSearchSchema = z.object({
    page: z.number().optional(),
    per_page: z.number().optional(),
    sort: z.string().optional(),
    order: z.enum(["asc", "desc"]).optional(),
    filter: z.string().optional(),
    status: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/cvs",
    validateSearch: cvsSearchSchema,
    beforeLoad: requirePermission([PERMISSIONS.CV_VIEW]),
    component: CvsBankPage,
});
