import { useEffect } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/features/auth/hooks/use-auth";
import {
    RUN_PROGRESS_EVENT,
    runStepOf,
    type RunProgress,
} from "@/features/realtime/types";
import { getEcho } from "@/lib/echo";
import { aiKeys } from "@/lib/query-keys";

/**
 * Which agents are running right now, for whoever asks.
 *
 * A run's progress is scratch state — true while it happens and meaningless
 * afterwards — but it is no longer *private* to the page that started it. A
 * summary run started on a meeting's page is exactly the thing an administrator
 * opening Settings wants to see, and tag consolidation is the same question in
 * another feature: "is the model being asked, and by whom".
 *
 * So the live map lives in the query cache rather than in component state. The
 * subscription below is mounted once by the shell, which means a page that was
 * never opened still knows a run is under way, and a page that unmounts does not
 * take the knowledge with it. Entries are dropped when their run reports a
 * terminal state; nothing here is persisted, so a reload starts from "nothing
 * running", which is the honest answer for a browser that has not yet heard an
 * event.
 *
 * The channel is the user's own (`users.{id}.runs`), which is what makes this
 * their activity rather than everybody's: a run somebody else started is not
 * this user's to see.
 */
export interface AgentActivity {
    runId: string;
    state: RunProgress["state"];
    /** The latest step key this run reported, or null before any. */
    step: string | null;
    /** Which feature started it, as the run's channel names it. */
    channel: string;
    subject: RunProgress["subject"];
    startedAt: string;
    /** The model name the run reported, when it named one. */
    model: string | null;
}

type ActivityMap = Readonly<Record<string, AgentActivity>>;

/**
 * Which agent a run belongs to, or null when it belongs to none.
 *
 * A run's subject names the thing it is about — a summary run's subject is
 * `board-meeting` — so the agent is read off the feature that started it. This
 * is a client-side mapping because the registry key is a client-side idea:
 * agents are addressed by key here, and the server's subject vocabulary belongs
 * to the feature (see `RunSubject`).
 *
 * The provider probe is deliberately unmapped: it is a registry check rather
 * than an agent doing work, and it already has its own row action in Settings.
 */
const CHANNEL_AGENT: Readonly<Record<string, string>> = {
    "board-meetings": "board-meeting.summary",
    tags: "tagging.consolidate",
};

export function agentKeyOfRun(progress: RunProgress): string | null {
    return CHANNEL_AGENT[progress.channel] ?? null;
}

/**
 * Record one event into the map.
 *
 * A terminal state removes the entry rather than keeping it: the durable record
 * is what a person reads after the fact, and a map that grew a tombstone per run
 * would put «پایان‌یافته» badges on rows nobody is waiting for.
 */
export function applyRunToActivity(
    current: ActivityMap | undefined,
    progress: RunProgress,
    now: string,
): ActivityMap {
    const agent = agentKeyOfRun(progress);

    if (agent === null) {
        return current ?? {};
    }

    const existing = current?.[agent];
    const continuing = existing?.runId === progress.run_id;

    if (progress.state === "finished" || progress.state === "failed") {
        if (existing === undefined) {
            return current ?? {};
        }

        const next = { ...current };

        delete next[agent];

        return next;
    }

    const reported = progress.context["model"];
    const step = runStepOf(progress)?.step ?? null;

    return {
        ...current,
        [agent]: {
            runId: progress.run_id,
            state: progress.state,
            // A state transition carries no step; keeping the last one means the
            // badge keeps saying what is happening instead of blanking out.
            step: continuing ? (step ?? existing.step) : step,
            channel: progress.channel,
            subject: progress.subject,
            startedAt: continuing ? existing.startedAt : now,
            model:
                typeof reported === "string"
                    ? reported
                    : continuing
                      ? existing.model
                      : null,
        },
    };
}

/**
 * Keeps `aiKeys.activity()` current for the whole app. Mounted once by the
 * shell; returns nothing on purpose so no page is tempted to subscribe again —
 * two listeners on one channel means two cache writes per event and an unmount
 * that can silence the other.
 */
export function RunActivityListener() {
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

        const onEvent = (progress: RunProgress): void => {
            if (agentKeyOfRun(progress) === null) {
                return;
            }

            queryClient.setQueryData<ActivityMap>(
                aiKeys.activity(),
                (current) =>
                    applyRunToActivity(
                        current,
                        progress,
                        new Date().toISOString(),
                    ),
            );
        };

        channel.listen(RUN_PROGRESS_EVENT, onEvent);

        return () => {
            channel.stopListening(RUN_PROGRESS_EVENT);
            echo.leaveChannel(channel.name);
        };
    }, [user, queryClient]);

    return null;
}

/** The agents currently running, keyed by registry key. */
export function useAgentActivity(): ActivityMap {
    const { data } = useQuery({
        queryKey: aiKeys.activity(),
        queryFn: () => ({}) as ActivityMap,
        staleTime: Infinity,
        gcTime: Infinity,
    });

    return data ?? {};
}
