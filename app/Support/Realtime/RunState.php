<?php

namespace App\Support\Realtime;

/**
 * How far along a long-running run is, from the only perspective that matters
 * to somebody waiting: is there still work outstanding?
 *
 * Three states, not a percentage, because the kernel cannot know what "half"
 * means. A job that calls a model once has one step and no halfway; a job that
 * walks a thousand rows has a thousand. A number here would be invented by the
 * sender and believed by the receiver, which is worse than saying "working".
 *
 * `Queued` and `Running` are deliberately distinct even though they look alike
 * on screen. They answer different questions - "has it started" versus "is it
 * under way" - and collapsing them makes a queue nobody is draining
 * indistinguishable from a slow model, which is the difference a waiting person
 * most needs to see.
 *
 * Terminal states are `Finished` and `Failed`. There is deliberately no
 * `Cancelled`: stopping a run mid-flight needs a cooperation token that the job
 * checks between steps, which this kernel cannot provide for a job it does not
 * own. A kernel naming a state it could never deliver would teach the next
 * caller to expect one.
 */
enum RunState: string
{
    /** Accepted and waiting for a worker. Nothing has happened yet. */
    case Queued = 'queued';

    /** A worker has it and is doing the work. */
    case Running = 'running';

    /** The work reported an outcome. Whether it was any good is the caller's business. */
    case Finished = 'finished';

    /** The work could not complete. */
    case Failed = 'failed';

    /**
     * Whether anything further is expected.
     *
     * The client's decision to stop listening rests on this rather than on
     * comparing values, so a state added later cannot quietly become terminal.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Queued, self::Running => false,
            self::Finished, self::Failed => true,
        };
    }
}
