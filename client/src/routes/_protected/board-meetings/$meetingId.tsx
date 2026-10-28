import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

const BoardMeetingDetailPage = lazy(() =>
    import(
        "@/features/board-meetings/pages/board-meeting-detail-page"
    ).then((m) => ({ default: m.BoardMeetingDetailPage })),
);

/**
 * The active tab, in the URL, as a closed set.
 *
 * Validated rather than read as `string`, because the page's tabs are partly
 * conditional — a meeting with no agenda has no agenda tab — so an unknown or
 * stale `?tab=` has to resolve to the default rather than render nothing. A
 * zod enum drops an unrecognised value back to `undefined`, which is exactly
 * the "fall back to the minutes" behaviour the page wants.
 */
const meetingSearchSchema = z.object({
    tab: z
        .enum(["preview", "analysis", "agenda", "attendees", "documents"])
        .optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/board-meetings/$meetingId",
    validateSearch: meetingSearchSchema,
    beforeLoad: requirePermission(PERMISSIONS.BOARD_MEETING_VIEW),
    component: () => (
        <LazyRoute
            component={BoardMeetingDetailPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
