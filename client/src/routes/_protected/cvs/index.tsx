import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const CvsBankPage = lazy(() =>
    import("@/features/cv/pages/cvs-bank-page").then((m) => ({ default: m.CvsBankPage }))
);

const cvsSearchSchema = paginatedSearchSchema({
    status: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/cvs",
    validateSearch: cvsSearchSchema,
    beforeLoad: requirePermission([PERMISSIONS.CV_VIEW]),
    component: () => (
        <LazyRoute component={CvsBankPage} fallback={<RouteLoadingFallback />} />
    ),
});
