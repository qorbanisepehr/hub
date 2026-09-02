import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const CvBankDetailPage = lazy(() =>
    import("@/features/cv/pages/cv-bank-detail-page").then((m) => ({ default: m.CvBankDetailPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/cvs/$id",
    beforeLoad: requirePermission([PERMISSIONS.CV_VIEW]),
    component: () => (
        <LazyRoute component={CvBankDetailPage} fallback={<RouteLoadingFallback />} />
    ),
});
