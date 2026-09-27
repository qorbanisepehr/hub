import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const QuestionnairesBankPage = lazy(() =>
    import("@/features/questionnaire/pages/questionnaires-bank-page").then((m) => ({ default: m.QuestionnairesBankPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/questionnaires",
    validateSearch: paginatedSearchSchema(),
    beforeLoad: requirePermission([PERMISSIONS.QUESTIONNAIRE_VIEW]),
    component: () => (
        <LazyRoute component={QuestionnairesBankPage} fallback={<RouteLoadingFallback />} />
    ),
});