import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const BoardMeetingDetailPage = lazy(() =>
    import(
        "@/features/board-meetings/pages/board-meeting-detail-page"
    ).then((m) => ({ default: m.BoardMeetingDetailPage })),
);

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/board-meetings/$meetingId",
    beforeLoad: requirePermission(PERMISSIONS.BOARD_MEETING_VIEW),
    component: () => (
        <LazyRoute
            component={BoardMeetingDetailPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
