/**
 * The realtime run-progress contract (`App\Support\Realtime`).
 *
 * Mirrors the server's `RunProgressed::broadcastWith()` exactly. The four
 * fields are the whole payload because the payload is a *nudge*: it names what
 * to re-read and says how far along the run is, and nothing about the run's
 * result. The result is read from the API, which is the source of truth - see
 * `RunProgressed`'s docblock for why that split is the design rather than a
 * limitation.
 */

/**
 * How far along a run is.
 *
 * `queued` and `running` are separate rather than merged because they answer
 * different questions: "has it started" versus "is it under way". Collapsing
 * them makes a queue nobody is draining look like a slow model, which is the
 * difference a waiting person most needs to see.
 *
 * There is no `cancelled`: the kernel cannot stop a run mid-flight, because
 * that needs a cooperation token in a job it does not own.
 */
export const RUN_STATES = [
    "queued",
    "running",
    "finished",
    "failed",
] as const;

export type RunState = (typeof RUN_STATES)[number];

/**
 * Whether nothing further is expected.
 *
 * Decided from the server's own list rather than by comparing against
 * `"finished"`, so a state added later cannot quietly become terminal by
 * omission on this side.
 */
const TERMINAL_STATES: ReadonlySet<RunState> = new Set<RunState>([
    "finished",
    "failed",
]);

export function isTerminalRunState(state: RunState): boolean {
    return TERMINAL_STATES.has(state);
}

/**
 * What a run is about, as a routing reference.
 *
 * The same `{type, id}` shape the notification contract already uses, matched
 * on purpose rather than reinvented. Never a URL and never the entity: the
 * client re-reads by its own route, so this exists only to say *which* one.
 *
 * The `id` stays whatever the server sent. Route params arrive as strings while
 * cached data arrives as numbers, so a client comparing them has to normalise -
 * which is better done in one visible helper than silently in a comparison.
 */
export interface RunSubject {
    type: string;
    id: string | number;
}

export interface RunProgress {
    /**
     * Identifies the run across its lifecycle, so a client can tell a
     * continuation from a different run about the same thing. For a summary
     * run this is the queue lock's own id, not a second invented one.
     */
    run_id: string;
    state: RunState;
    /**
     * The client's cache/query key to re-read under. The client owns this
     * vocabulary; the kernel only carries it.
     */
    channel: string;
    /**
     * What this run is about, so a client watching several at once can tell
     * them apart. Always present, null included - a run that is genuinely about
     * nothing says so rather than omitting the key.
     */
    subject: RunSubject | null;
    /**
     * Caller-defined detail for the message itself - a count, a status, an
     * error key. Never the entity itself.
     */
    context: Record<string, unknown>;
    occurred_at: string;
}

/**
 * One entry in a run's trail: which step it reported, and when.
 *
 * The step is the caller's own stable key — for a board meeting summary,
 * `preparing` / `prompting` / `calling_model` / `parsing` / `storing`. The
 * realtime kernel neither defines that vocabulary nor translates it, for the
 * same reason it does not define status words: a feature owns its own keys and
 * its own copy of them. Keeping them out of here is what lets a second
 * feature report steps of its own without this file growing a union it would
 * then have to widen every time.
 */
export interface RunStep {
    step: string;
    at: string;
}

/**
 * Read the step out of a progress event's context, or null when the event is
 * not reporting one (a state change, or a terminal outcome).
 *
 * A string only: anything else in `context` belongs to whichever field made the
 * event, and guessing at its shape here would be the kernel reading a feature's
 * vocabulary.
 */
export function runStepOf(progress: RunProgress): RunStep | null {
    const step = progress.context["step"];

    return typeof step === "string"
        ? { step, at: progress.occurred_at }
        : null;
}

/** The generic event name, so clients key off the payload not the class. */
export const RUN_PROGRESS_EVENT = ".run.progressed";
