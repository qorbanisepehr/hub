import { useEffect } from "react";
import { useQueryClient, type QueryClient } from "@tanstack/react-query";

import { useAuth } from "@/features/auth/hooks/use-auth";
import { getEcho } from "@/lib/echo";

import {
    RUN_PROGRESS_EVENT,
    isTerminalRunState,
    type RunProgress,
    type RunState,
} from "./types";

/**
 * React to long-running runs the signed-in user started.
 *
 * The whole design is that this is a nudge, not a feed. Every event's only
 * obligation is to make the client *re-read* the thing it names, exactly as the
 * notification centre re-reads its queries on `notification.created`. Nothing
 * here renders a run's outcome, because a browser that was closed, a websocket
 * that dropped, or a deployment with no Reverb configured all mean the same
 * thing: events were missed. A UI that trusted them would show a run as
 * finished that never finished, and would still be showing it after a refresh.
 *
 * So progress is only ever a timing optimisation over data the client could
 * fetch anyway. That is also why there is no polling fallback here: the queries
 * this invalidates already have their own staleness behaviour, and a second
 * timer would be a second thing to reason about for no gain.
 *
 * One private channel per user for every kind of run (`users.{id}.runs`), so a
 * feature needing live progress subscribes here rather than opening another
 * connection. `getEcho()` returns null when Reverb is not configured, which
 * makes this a safe no-op rather than a crash.
 */
export function useRunProgressChannel(
    onProgress?: (progress: RunProgress) => void,
) {
    const { user } = useAuth();
    const queryClient = useQueryClient();

    useEffect(() => {
        if (!user) {
            return;
        }

        const echo = getEcho();
        if (!echo) {
            return;
        }

        const channel = echo.private(`users.${user.id}.runs`);

        channel.listen(RUN_PROGRESS_EVENT, (progress: RunProgress) => {
            reRunAffectedQueries(queryClient, progress);

            onProgress?.(progress);
        });

        return () => {
            channel.stopListening(RUN_PROGRESS_EVENT);
            echo.leaveChannel(channel.name);
        };
    }, [user, queryClient, onProgress]);
}

/**
 * Invalidate whatever the event says changed.
 *
 * Keyed by the `channel` the caller chose, so this function needs no knowledge
 * of any feature: the job named the key its result appears under, and that is
 * the one thing worth re-reading. A `null` or empty key invalidates nothing,
 * which is the safe reading - a malformed event should not cause a full cache
 * flush on the chance that something moved.
 */
function reRunAffectedQueries(
    queryClient: QueryClient,
    progress: RunProgress,
): void {
    if (progress.channel === "") {
        return;
    }

    void queryClient.invalidateQueries({ queryKey: [progress.channel] });
}

/**
 * A short human sentence for a run state, for a toast.
 *
 * Translated on this side rather than sent by the server, like every other
 * string in this client: the payload carries the state and the server's own
 * keys, and the words are the UI's business.
 */
export function runStateLabel(state: RunState): string {
    switch (state) {
        case "queued":
            return "در صف اجرا قرار گرفت.";
        case "running":
            return "در حال اجرا...";
        case "finished":
            return "پایان یافت.";
        case "failed":
            return "ناموفق بود.";
    }
}

/** Whether a state is one the UI should stop waiting on. */
export { isTerminalRunState };
