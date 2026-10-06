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
     * Caller-defined detail for the message itself - a count, a status, an
     * error key. Never the entity itself.
     */
    context: Record<string, unknown>;
    occurred_at: string;
}

/** The generic event name, so clients key off the payload not the class. */
export const RUN_PROGRESS_EVENT = ".run.progressed";
