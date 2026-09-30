<?php

namespace App\Domains\BoardMeeting\Jobs;

use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSummarizer;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate a summary for one meeting and store it on the row (D23).
 *
 * Queued because a meeting's minutes can run to thousands of words and the
 * registry holds hundreds of them: the bulk action must return to the user
 * immediately, and one slow model must not hold the request open. Uniqueness
 * is per (meeting, field set) so a second click while the first run is still
 * in flight is dropped rather than paid for twice — but asking for a
 * different subset is a different request and is *not* dropped, because that
 * is D26's whole point.
 *
 * The row is stamped `pending` before the model is called. Under the
 * database queue a bulk action leaves most of the registry in flight at once,
 * and without that stamp "queued" and "never asked" would look identical in
 * the detail view. It is also the honest state if this process dies
 * mid-run: the meeting says a run started and did not report.
 *
 * Only `AiNotConfigured` is caught, because it is the one failure a retry
 * cannot fix — there is no provider to call. Everything else (a dead URL, a
 * rejected key, a timeout) propagates so the queue retries it, and the
 * meeting keeps its `pending` stamp until a run does report.
 */
class GenerateBoardMeetingSummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Release the lock in case the worker dies holding it. */
    public int $uniqueFor = 900;

    /**
     * @param  list<string>  $fields  empty means every field
     * @param  string|null  $model  a model name chosen for this run in the UI
     */
    public function __construct(
        public readonly int $meetingId,
        public readonly array $fields = [],
        public readonly ?string $model = null,
    ) {}

    /**
     * Identity for uniqueness: the meeting plus what it was asked for.
     *
     * The subset is sorted so `['topics', 'key_decisions']` and
     * `['key_decisions', 'topics']` are recognised as the same request — the
     * order a client happened to send is not a different run, and treating it
     * as one would let a duplicate through the very lock meant to stop it.
     */
    public function uniqueId(): string
    {
        $fields = $this->fields;

        sort($fields);

        return $this->meetingId.'|'.implode(',', $fields);
    }

    public function handle(BoardMeetingSummarizer $summarizer): void
    {
        $meeting = BoardMeeting::query()->find($this->meetingId);

        if ($meeting === null) {
            return;
        }

        $this->store($meeting, MeetingSummary::pending());

        try {
            $summary = $summarizer->summarize($meeting, $this->fields, $this->model);
        } catch (AiNotConfigured) {
            $this->store($meeting, MeetingSummary::failure(MeetingSummary::REASON_NOT_CONFIGURED));

            return;
        }

        $this->store($meeting, $summary);
    }

    /**
     * The write is the meeting's own column and nothing else.
     *
     * `summary` is deliberately outside `HUMAN_COLUMNS`, so this can never
     * freeze the source's view of the row: a summary is regenerated on a
     * locked meeting on purpose, which is the whole point of regenerating it.
     *
     * Every write folds into whatever the row already held rather than
     * replacing it, including the `pending` stamp — a bulk action over the
     * registry must not blank two hundred meetings while their runs are in
     * flight.
     */
    private function store(BoardMeeting $meeting, MeetingSummary $summary): void
    {
        $meeting->forceFill([
            'summary' => $summary->mergedOver(MeetingSummary::fromArray($meeting->summary))->toArray(),
        ])->save();
    }
}
