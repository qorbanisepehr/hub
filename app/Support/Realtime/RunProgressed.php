<?php

namespace App\Support\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A long-running run told the person who started it that it moved.
 *
 * The event is a *nudge*, not a source of truth, and that is the whole design.
 * Whatever the run is doing must already be readable from somewhere durable -
 * a status on a row, a document, a file - because the browser may not be
 * listening at all: the tab was closed, the websocket dropped, Reverb was never
 * configured. A client that trusted this payload would show a run as finished
 * that never finished, and would keep showing it after a refresh, because the
 * payload outlives nothing.
 *
 * So the payload names *what* to look at and *how far* along it is, and the
 * client's only obligation is to re-read the thing. This is the same split the
 * notification centre already uses: the database row is the truth, the
 * broadcast makes the UI react now instead of at the next poll.
 *
 * Carries no entity. A progress event naming a model would couple every future
 * run to the shape of whatever it is reporting on, and the re-read is going to
 * fetch that entity by its own route anyway. What travels is a reference the
 * client can key its cache on.
 *
 * Private, and per-user rather than per-role: a run belongs to the person who
 * paid for it. A 200-row bulk would otherwise broadcast 200 messages to every
 * administrator who can see the list, none of whom asked for it and all of whom
 * would have to filter it out.
 */
class RunProgressed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  string  $runId  identifies the run across its queued/running/finished
     *                         lifecycle, so a client can tell a continuation
     *                         from a different run that happens to be about
     *                         the same thing
     * @param  string  $channel  the query/cache key the client re-reads under;
     *                           the client owns the vocabulary, this kernel
     *                           only carries it
     * @param  array<string, mixed>  $context  caller-defined detail for the
     *                                         message itself - a count, a label, an error.
     *                                         Never the entity: see the class docblock.
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $runId,
        public readonly RunState $state,
        public readonly string $channel,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelFor($this->userId))];
    }

    /**
     * The person's private run channel.
     *
     * One channel per user for every kind of run, rather than one per kind of
     * run per user: a second feature wanting live progress should subscribe to
     * the channel it already has, not ask for another authorization rule and
     * another Echo connection. The `run` segment is registered without the
     * `private-` prefix because PusherBroadcaster strips it before matching.
     */
    public static function channelFor(int $userId): string
    {
        return "users.{$userId}.runs";
    }

    /**
     * A generic name so clients key off the payload rather than the class, the
     * same reasoning as the notification centre's `notification.created`.
     */
    public function broadcastAs(): string
    {
        return 'run.progressed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'state' => $this->state->value,
            'channel' => $this->channel,
            'context' => $this->context,
            'occurred_at' => now()->toISOString(),
        ];
    }
}
