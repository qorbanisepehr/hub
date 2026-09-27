import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { paginatedSearchSchema } from "@/lib/zod-primitives";

const QuestionnairesBankPage = lazy(() =>
    import("@/features/questionnaire/pages/questionnaires-bank-page").then((m) => ({ default: m.QuestionnairesBankPage }))
);

const questionnairesSearchSchema = paginatedSearchSchema({
    status: z.string().optional(),
    status_not: z.string().optional(),
    gender: z.string().optional(),
    marital_status: z.string().optional(),
    employment_type: z.string().optional(),
    currently_employed: z.string().optional(),
    mobile_verified: z.string().optional(),
    email_verified: z.string().optional(),
    date_from: z.string().optional(),
    date_to: z.string().optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/questionnaires",
    validateSearch: questionnairesSearchSchema,
    beforeLoad: requirePermission([PERMISSIONS.QUESTIONNAIRE_VIEW]),
    component: () => (
        <LazyRoute component={QuestionnairesBankPage} fallback={<RouteLoadingFallback />} />
    ),
});