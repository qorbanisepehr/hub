import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const QuestionnaireBankDetailPage = lazy(() =>
    import("@/features/questionnaire/pages/questionnaire-bank-detail-page").then((m) => ({ default: m.QuestionnaireBankDetailPage }))
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/questionnaires/$id",
    beforeLoad: requirePermission([PERMISSIONS.QUESTIONNAIRE_VIEW]),
    component: () => (
        <LazyRoute component={QuestionnaireBankDetailPage} fallback={<RouteLoadingFallback />} />
    ),
});