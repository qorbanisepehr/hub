import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { CvBankDetailPage } from "@/features/cv/pages/cv-bank-detail-page";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/cvs/$id",
    beforeLoad: requirePermission([PERMISSIONS.CV_VIEW]),
    component: CvBankDetailPage,
});
