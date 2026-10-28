import { IconCheck, IconClock, IconPlayerStopFilled, IconX } from "@tabler/icons-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import {
    Alert,
    AlertAction,
    AlertDescription,
    AlertTitle,
} from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import type { RunProgress, RunState, RunStep } from "@/features/realtime/types";

import { summaryStepDoneLabel, summaryStepLabel } from "../steps";

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
 * Only the live half is drawn here. `queued` and `running` are states somebody
 * is actively waiting in, so they get a durable block that survives a refresh.
 * The two terminal states are news rather than status — they are delivered as
 * toasts and leave nothing behind, which is why a meeting that already carries
 * a summary no longer opens on a banner announcing that its run finished long
 * ago. A failure is announced on load as well, because unlike success it is
 * not self-evident from what is on the page.
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
 *
 * Two inputs, deliberately: a live event says *which* run and how it is going,
 * while the row's own start time says when it began. The first is fresher and
 * the second outlives it - a refresh or a second tab has never seen an event,
 * and reporting on the row alone is why the block survives one.
 */
export function RunStatus({
    state,
    run,
    steps,
    startedAt,
    errorReason,
    onCancel,
    isCancelling,
    className,
}: {
    /** What the durable row says, used when no live event has arrived yet. */
    state: RunState | null;
    /** The latest progress event, when there is one. */
    run: RunProgress | null;
    /**
     * The steps this run has reported, oldest first.
     *
     * Drawn as a trail under the status line, which is what turns a minute of
     * spinner into a minute of something happening. Optional because a run that
     * has only just been dispatched has none yet, and because a page loaded
     * after the fact never saw any — the row alone is still a correct answer,
     * just a coarser one.
     */
    steps?: readonly RunStep[];
    /** When the run under way began, from the row, or null. */
    startedAt?: string | null;
    /**
     * The durable failure reason, read off the row.
     *
     * Carried in for the toast rather than drawn inline: a failed run used to
     * leave both a status block and a separate destructive alert saying the
     * same thing, and the reason belongs on the one notice that reports the
     * failure.
     */
    errorReason?: string | null;
    /**
     * Stop this run, when the viewer may. Absent means the block is a report
     * rather than a control — and the block only ever draws a run that is still
     * going, so a gesture that is offered here is always one with something to
     * stop.
     */
    onCancel?: () => void;
    /** The stop request is in flight. */
    isCancelling?: boolean;
    className?: string;
}) {
    // `run` wins over `state`: an event is fresher than anything the row said
    // when the tab was last fetched. Before the first event arrives - or when
    // nobody is listening, since this degrades to the row alone - the row is
    // still a correct answer, just a coarser one.
    const shown = run?.state ?? state ?? null;
    const since = run?.occurred_at ?? startedAt ?? null;

    /**
     * What is currently on screen, as far as dismissal is concerned.
     *
     * A notice somebody has closed should stay closed, but only for as long as
     * it is *the same notice*: a state change, a second run, or a run reported
     * from a different origin all mean the question is being asked again, and a
     * dismissal carried across those would hide something nobody has seen yet.
     * `run_id` is the queue's own lock id, so it changes exactly when a new run
     * does and never when a query is merely refetched.
     */
    const signature = [shown ?? "", run?.run_id ?? "", since ?? ""].join("|");
    const [dismissed, setDismissed] = useState<string | null>(null);
    const announced = useRef<string | null>(null);

    const terminal = shown === "finished" || shown === "failed";

    /**
     * A run's end is news, so it is announced once and not drawn.
     *
     * A success is only news while it is happening: a stored `ok` row would
     * otherwise toast on every page load, which is the banner this replaced in
     * another costume. A failure is announced either way, because a reload is
     * exactly when somebody would want to be told the run did not work.
     */
    useEffect(() => {
        if (!terminal || announced.current === signature) {
            return;
        }

        if (shown === "finished" && run === null) {
            return;
        }

        announced.current = signature;

        if (shown === "finished") {
            toast.success("اجرا تمام شد", {
                description: "نتیجه در همین‌جا به‌روز شد.",
            });

            return;
        }

        toast.error("اجرا ناموفق بود", {
            description:
                errorReason ??
                "این اجرا تلاش دوباره نمی‌شود؛ می‌توانید دوباره اجرا بگیرید.",
        });
    }, [terminal, shown, signature, run, errorReason]);

    // The end of a run is delivered as a toast above; only the live states are
    // drawn here.
    if (shown === null || terminal || dismissed === signature) {
        return null;
    }

    const waiting = shown === "queued";

    // The newest step is the current position; everything before it is history.
    // The current one is drawn in the status line itself rather than repeated
    // as a trail row, so the two never say the same thing twice.
    const trail = (steps ?? []).slice(0, -1).toReversed();
    const current = steps?.at(-1)?.step;

    return (
        <Alert variant="default" className={className}>
            {waiting ? <IconClock /> : <Spinner />}

            <AlertTitle>
                {waiting
                    ? "در صف اجرا"
                    : current !== undefined
                      ? summaryStepLabel(current)
                      : "در حال تولید خلاصه"}
            </AlertTitle>

            <AlertAction>
                {onCancel && (
                    <Button
                        size="icon-xs"
                        disabled={isCancelling}
                        onClick={onCancel}
                        aria-label="توقف اجرا"
                        className="me-1"
                    >
                        {isCancelling ? (
                            <Spinner className="size-3" />
                        ) : (
                            <IconPlayerStopFilled />
                        )}
                    </Button>
                )}

                <Button
                    variant="outline"
                    size="icon-xs"
                    aria-label="بستن این پیام"
                    onClick={() => setDismissed(signature)}
                >
                    <IconX />
                </Button>
            </AlertAction>

            <AlertDescription className="flex flex-wrap items-center gap-x-3 gap-y-1">
                {waiting ? (
                    <span>هنوز کارگری این اجرا را برنداشته است.</span>
                ) : (
                    <span>نتیجه همین‌جا به‌روز می‌شود.</span>
                )}

                {!waiting && since !== null && <Elapsed since={since} />}

                {run?.context?.model != null && (
                    <Badge variant="secondary" className="font-normal">
                        {String(run.context.model)}
                    </Badge>
                )}
            </AlertDescription>

            {trail.length > 0 && (
                <ol className="mt-2 flex flex-col gap-1 ps-6 text-xs text-muted-foreground">
                    {trail.map((step) => (
                        <li
                            key={`${step.step}-${step.at}`}
                            className="flex items-center gap-1.5"
                        >
                            <IconCheck className="size-3 shrink-0" />
                            {summaryStepDoneLabel(step.step)}
                        </li>
                    ))}
                </ol>
            )}
        </Alert>
    );
}

/**
 * Time since the run was reported as under way.
 *
 * Ticks locally rather than on a server round trip: the number is for reading,
 * not for deciding, and a per-second request would be absurd for that. The
 * starting point comes from whichever of the two inputs has it - a live event
 * when one arrived, the row's own stamp when the page was just opened - so this
 * measures "how long since this run began" either way, and a tab that learned
 * of the run from the database counts from the same moment a tab that heard it
 * on the socket would.
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

    return h > 0 ? `${h}:${pad2(m)}:${pad2(s)}` : `${m}:${pad2(s)}`;
}

function pad2(n: number): string {
    return String(n).padStart(2, "0");
}
