import { useCallback, useEffect, useRef, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/features/auth/hooks/use-auth";
import { getEcho } from "@/lib/echo";

import {
    RUN_PROGRESS_EVENT,
    type RunProgress,
} from "./types";

/**
 * Live progress for the long-running runs this user started.
 *
 * Two things are deliberately kept apart here, because they have nothing in
 * common but the channel they arrive on.
 *
 * **State transitions** (`run.progressed`) are few, and they invalidate queries.
 * The event names the cache key to re-read and nothing else - never a result -
 * so a client that missed one, or was closed while it happened, simply shows
 * slightly stale data until the next fetch. That is the same split the
 * notification centre uses: the database row is the truth, the broadcast only
 * makes the UI react now rather than at the next poll. It is why there is no
 * persistence here and no read model; a browser with no websocket, a dropped
 * connection, or a deployment with no Reverb all degrade to "fetch later"
 * instead of to a lie.
 *
 * **Run states are also delivered per subject**, so a client watching several
 * runs at once - the bulk dialog, say - can say which one is running and which
 * are still queued. A cache key alone cannot answer that: ten runs share one
 * prefix, so without a subject every event reads as "something moved".
 *
 * No polling fallback, unlike notifications. The queries this invalidates
 * already carry their own staleness behaviour, and a second timer would be a
 * second thing to reason about for no gain.
 */
export function useRunProgressChannel(
    onProgress?: (progress: RunProgress) => void,
) {
    const { user } = useAuth();
    const queryClient = useQueryClient();

    // Held in a ref so the subscription does not tear down and rebuild every
    // time the caller passes a new callback - which for an inline arrow is
    // every render, and would mean dropping and re-adding the channel
    // constantly enough to drop real events between the two.
    const handler = useRef(onProgress);
    handler.current = onProgress;

    useEffect(() => {
        if (!user) {
            return;
        }

        const echo = getEcho();
        if (!echo) {
            return;
        }

        const channel = echo.private(`users.${user.id}.runs`);

        const onEvent = (progress: RunProgress): void => {
            reRunAffectedQueries(queryClient, progress);
            handler.current?.(progress);
        };

        channel.listen(RUN_PROGRESS_EVENT, onEvent);

        return () => {
            channel.stopListening(RUN_PROGRESS_EVENT);
            echo.leaveChannel(channel.name);
        };
    }, [user, queryClient]);
}

/**
 * The per-subject run states one user currently has in flight.
 *
 * Returned as a map keyed by `type:id` rather than as a list, because the
 * question a client asks is always "what is this one doing?" - the bulk dialog
 * renders a row per meeting and looks each one up. A list would make every row
 * scan every event.
 *
 * Scratch state, deliberately never cached: a run's progress is true while it
 * is happening and meaningless afterwards, so putting it in the query cache
 * would mean reconciling or expiring it - and the moment it stops mattering is
 * the moment the durable record takes over. Dropping it on unmount is the
 * correct lifetime, not a limitation.
 *
 * @returns a map of subject key to the latest state seen for it
 */
export function useRunStates(): {
    states: ReadonlyMap<string, RunProgress>;
    track: (progress: RunProgress) => void;
    forget: (key: string) => void;
    reset: () => void;
} {
    const [states, setStates] = useState<ReadonlyMap<string, RunProgress>>(
        new Map(),
    );

    const track = useCallback((progress: RunProgress): void => {
        const key = runSubjectKey(progress);

        // An event with no subject is not about any one thing, so there is
        // nothing to file it under. Still delivered to `onProgress` above -
        // this map is only for per-subject lookup.
        if (key === null) {
            return;
        }

        setStates((previous) => {
            const next = new Map(previous);

            next.set(key, progress);

            return next;
        });
    }, []);

    const forget = useCallback((key: string): void => {
        setStates((previous) => {
            if (!previous.has(key)) {
                return previous;
            }

            const next = new Map(previous);

            next.delete(key);

            return next;
        });
    }, []);

    const reset = useCallback((): void => {
        setStates(new Map());
    }, []);

    return { states, track, forget, reset };
}

/** The map key for a run's subject, or null when it names nothing. */
export function runSubjectKey(progress: RunProgress): string | null {
    const subject = progress.subject;

    if (subject == null) {
        return null;
    }

    return `${subject.type}:${subject.id}`;
}

/**
 * Invalidate whatever the event says changed.
 *
 * Keyed by the `channel` the run chose, so this needs no knowledge of any
 * feature: the job named the key its result appears under, and that is the one
 * thing worth re-reading. An empty key invalidates nothing, which is the safe
 * reading - a malformed event should not flush somebody's whole cache on the
 * chance that something moved.
 */
function reRunAffectedQueries(
    queryClient: ReturnType<typeof useQueryClient>,
    progress: RunProgress,
): void {
    if (progress.channel === "") {
        return;
    }

    void queryClient.invalidateQueries({ queryKey: [progress.channel] });
}
