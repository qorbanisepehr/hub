import { useEffect, useState } from "react";
import { IconClock, IconInfoCircle, IconRobot } from "@tabler/icons-react";

import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";
import type { RunProgress, RunState } from "@/features/realtime/types";

/**
 * What the run is doing right now, for somebody waiting on it.
 *
 * Replaces a bare spinner, and the reason it can say more is that the run
 * reports real states rather than one indistinguishable "busy". `queued` and
 * `running` mean different things and are worth telling apart: the first says
 * no worker has picked this up yet, the second says the model is being asked.
 * Collapsing them hides the case where a queue is not draining, which is the
 * one thing a waiting person most needs to see.
 *
 * Deliberately absent: a percentage, a step counter, a per-field progress bar.
 * The summary is produced by one model call, so there is no such thing to
 * report - a bar would be measuring nothing, and a number that looks like
 * progress trains people to trust it. Elapsed time is shown instead, which is
 * the one honest measure available and is genuinely useful for deciding whether
 * to wait or to go and do something else.
 *
 * Which model is answering is shown because the row records it, and because
 * "it is slow" and "it is slow *on that model*" call for different reactions.
 */
export function RunStatus({
    state,
    run,
    className,
}: {
    /** What the durable row says, used when no live event has arrived yet. */
    state: RunState | null;
    /** The latest progress event, when there is one. */
    run: RunProgress | null;
    className?: string;
}) {
    // `run` wins over `state`: an event is fresher than anything the row said
    // when the tab was last fetched. Before the first event arrives - or when
    // nobody is listening, since this degrades to the row alone - the row is
    // still a correct answer, just a coarser one.
    const shown = run?.state ?? state ?? null;
    const startedAt = run?.occurred_at ?? null;

    if (shown === null) {
        return null;
    }

    const waiting = shown === "queued";
    const done = shown === "finished";
    const failed = shown === "failed";

    return (
        <Alert
            variant={failed ? "destructive" : "default"}
            className={cn(done && "border-success/40 bg-success/5", className)}
        >
            {waiting ? (
                <IconClock />
            ) : failed ? (
                <IconInfoCircle />
            ) : done ? (
                <IconRobot />
            ) : (
                <Spinner />
            )}

            <AlertTitle>
                {waiting
                    ? "در صف اجرا"
                    : done
                      ? "اجرا تمام شد"
                      : failed
                        ? "اجرا ناموفق بود"
                        : "در حال تولید خلاصه"}
            </AlertTitle>

            <AlertDescription className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span>
                    {waiting
                        ? "هنوز کارگری این اجرا را برنداشته است."
                        : done
                          ? "نتیجه همین‌جا به‌روز شد."
                          : failed
                            ? "این اجرا تلاش دوباره نمی‌شود؛ می‌توانید دوباره اجرا بگیرید."
                            : "مدل در حال پاسخ دادن است؛ نتیجه همین‌جا به‌روز می‌شود."}
                </span>

                {!waiting && !done && !failed && startedAt !== null && (
                    <Elapsed since={startedAt} />
                )}

                {run?.context?.model != null && (
                    <Badge variant="secondary" className="font-normal">
                        {String(run.context.model)}
                    </Badge>
                )}
            </AlertDescription>
        </Alert>
    );
}

/**
 * Time since the run was reported as under way.
 *
 * Ticks locally rather than on a server round trip: the number is for reading,
 * not for deciding, and a per-second request would be absurd for that. The
 * starting point comes from the event that arrived, so this measures "how long
 * since you heard from it" - which for a run whose events have stopped is
 * exactly the useful thing to show.
 */
function Elapsed({ since }: { since: string }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    const seconds = Math.max(
        0,
        Math.round((now - new Date(since).getTime()) / 1000),
    );

    return (
        <span
            dir="ltr"
            className="text-muted-foreground font-mono text-xs tabular-nums"
        >
            {formatElapsed(seconds)}
        </span>
    );
}

/** `M:SS` while it is short, `H:MM:SS` once it is not. */
function formatElapsed(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    return h > 0
        ? `${h}:${pad2(m)}:${pad2(s)}`
        : `${m}:${pad2(s)}`;
}

function pad2(n: number): string {
    return String(n).padStart(2, "0");
}
