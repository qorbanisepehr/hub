import { lazy } from "react";
import { createRoute } from "@tanstack/react-router";
import { z } from "zod";
import { Route as ProtectedRoute } from "@/routes/_protected";
import { LazyRoute, RouteLoadingFallback } from "@/components/layout/lazy-route";
import { requirePermission } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";
import { csvArray, paginatedSearchSchema } from "@/lib/zod-primitives";

const BoardMeetingsPage = lazy(() =>
    import("@/features/board-meetings/pages/board-meetings-page").then((m) => ({
        default: m.BoardMeetingsPage,
    })),
);

const boardMeetingsSearchSchema = paginatedSearchSchema({
    // Comma-tolerant so `?tags=budget` is a valid link. The detail page's tag
    // chips navigate straight here, and a URL only a router can parse is not a
    // link anybody can share.
    tags: csvArray(),
    date_from: z.string().optional(),
    date_to: z.string().optional(),
    // Which rendering of the same rows the list shows (D15). Absent means the
    // table; it is URL state so a card or calendar view is shareable.
    view: z.enum(["table", "card", "calendar"]).optional(),
});

export const Route = createRoute({
    getParentRoute: () => ProtectedRoute,
    path: "/board-meetings",
    validateSearch: boardMeetingsSearchSchema,
    beforeLoad: requirePermission(PERMISSIONS.BOARD_MEETING_VIEW),
    component: () => (
        <LazyRoute
            component={BoardMeetingsPage}
            fallback={<RouteLoadingFallback />}
        />
    ),
});
