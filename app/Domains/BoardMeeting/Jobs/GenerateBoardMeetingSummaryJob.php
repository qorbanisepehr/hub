<?php

namespace App\Domains\BoardMeeting\Jobs;

use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Events\MeetingSummaryGenerated;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSummarizer;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Support\Realtime\Concerns\ReportsRunProgress;
use App\Support\Realtime\RunState;
use App\Support\Realtime\Value\RunSubject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate a summary for one meeting (D23).
 *
 * Queued because a meeting's minutes can run to thousands of words and the
 * registry holds hundreds of them: the bulk action must return to the user
 * immediately, and one slow model must not hold the request open. Uniqueness
 * is per (meeting, field set, mode) so a second click while the first run is
 * still in flight is dropped rather than paid for twice — but asking for a
 * different subset is a different request and is *not* dropped, because that
 * is D26's whole point. The mode is part of the identity for the same reason:
 * "run it and put it in the record" and "run it and hold it for me" are two
 * different things to ask for, and silently giving someone the first when they
 * asked for the second is exactly the surprise the mode exists to prevent.
 *
 * The row is stamped `pending` before the model is called. Under the
 * database queue a bulk action leaves most of the registry in flight at once,
 * and without that stamp "queued" and "never asked" would look identical in
 * the detail view. It is also the honest state if this process dies
 * mid-run: the meeting says a run started and did not report.
 *
 * Only `AiNotConfigured` is caught, because it is the one failure a retry
 * cannot fix — there is no provider to call. Everything else (a dead URL, a
 * rejected key, a timeout) propagates, and the meeting keeps its `pending`
 * stamp while the queue decides whether to try again: D22 asks for an event
 * per *run*, and a run still being retried has not finished.
 *
 * That leaves the case this class originally had no answer for — the queue
 * giving up. A transport failure escaping `handle()` used to end the job with
 * the row still stamped `pending`, no terminal progress event and no
 * notification, so a run that died looked exactly like a run still going and
 * told nobody. The worker retries nothing by default (`queue:listen --tries=1`
 * in `composer run dev`), so in practice that was every timeout. `failed()`
 * closes it: called once, after the last attempt, so a run merely being
 * retried still stays quiet.
 */
class GenerateBoardMeetingSummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsRunProgress;
    use SerializesModels;

    /**
     * How long the uniqueness lock outlives a run, in seconds.
     *
     * Its placement is the whole point: above the job's own `timeout`, and
     * below the queue's `retry_after`. Below `timeout`, a run still going
     * legitimately would lose its lock part way through and a second click
     * would start the same work twice - the exact thing the lock exists to
     * prevent. Above `retry_after`, a worker that died holding the lock would
     * leave it in place when the queue re-dispatched that job, and the
     * re-dispatch would be dropped as a duplicate: the run would then never
     * happen at all, with the row sitting `pending` and nothing left in the
     * system to fail it.
     *
     * Both neighbours, and why all three move together, are documented in
     * {@see BoardMeetingSummarizer}'s timeout docblock.
     */
    public int $uniqueFor = 1200;

    /**
     * Give up on this job after eighteen minutes.
     *
     * Must sit above the summarizer's own 900s HTTP timeout, or the worker
     * would kill the job while the model was still answering - losing a run
     * that was about to succeed. In practice the HTTP timeout fires first and
     * this is only the backstop for a call that does not answer it (a hung
     * socket, a gateway that accepts and then never replies), which is where
     * the 180s of margin comes from rather than it being spare.
     *
     * It also has to sit below the queue's `retry_after`, which is a different
     * failure: that one re-dispatches a job it believes died, so a run longer
     * than `retry_after` gets paid for twice. That number, and the `uniqueFor`
     * between them, are documented together in
     * {@see BoardMeetingSummarizer}'s timeout docblock because none of the
     * three can be changed independently of the others.
     *
     * `$failOnTimeout` stays false on purpose. A timeout here is transient
     * infrastructure - a model still loading, a machine busy - and unlike
     * `AiNotConfigured` there is no number of retries that would fix it, so it
     * should reach the queue's own retry policy rather than being abandoned on
     * the first attempt.
     */
    public int $timeout = 1080;

    /**
     * @param  list<string>  $fields  empty means every field
     * @param  string|null  $provider  a gateway picked for this one run in the
     *                                 UI, or null to inherit. Names rather
     *                                 than ids: a model name is unique only
     *                                 within a provider, and a row id that
     *                                 implied the provider too meant the
     *                                 choice could never be expressed as
     *                                 "this model, on that gateway"
     * @param  string|null  $model  a model picked for this one run in the UI, or
     *                              null to inherit. Kept lenient on purpose —
     *                              a name that no longer resolves falls back to
     *                              the agent's binding, so a job queued against a
     *                              model somebody deleted in Settings produces a
     *                              degraded summary instead of failing
     * @param  bool  $merge  reconcile the requested fields with what the record
     *                       already holds, rather than writing them from the
     *                       minutes alone. Part of the identity below, because
     *                       "write this field fresh" and "improve this field"
     *                       are two different requests and the second must not
     *                       be swallowed as a duplicate of the first.
     * @param  int|null  $requestedBy  who asked for this run, so it can report
     *                                 progress to them. Nullable because only
     *                                 an HTTP dispatch has a user behind it -
     *                                 a scheduled or CLI path has nobody
     *                                 watching, and inventing an id would put a
     *                                 run's progress on a stranger's channel.
     *                                 Null just means nothing is reported; the
     *                                 outcome is written to the row either way
     * @param  string|null  $batchId  set when this run is one of a bulk
     *                                dispatch, so a batch can be summarised once
     *                                at the end rather than notifying per meeting.
     *                                Deliberately *not* part of `uniqueId()`:
     *                                asking for the same summary again after a
     *                                bulk is the same request as asking for it
     *                                the first time, and the lock should treat
     *                                it that way
     */
    public function __construct(
        public readonly int $meetingId,
        public readonly array $fields = [],
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly SummaryWriteMode $mode = SummaryWriteMode::Review,
        public readonly bool $merge = false,
        public readonly ?int $requestedBy = null,
        public readonly ?string $batchId = null,
    ) {
        // The run id is the queue lock's identity, reused rather than invented a
        // second time: the client needs to recognise a continuation of *this*
        // run, and the lock's id already answers exactly that. A separate id
        // would be a second thing that has to agree with the first.
        //
        // The channel is the client's cache prefix rather than something finer
        // grained on purpose. A summary lands on the meeting row, so the detail
        // query has to be re-read; a bulk run moves the list's own columns too,
        // so the list has to be. One prefix covers both, and naming the exact
        // query here would mean this job had to know the client's cache layout
        // and keep it in step.
        //
        // The subject is what makes a bulk run legible. Ten of these jobs share
        // that one prefix, so without saying which meeting each event is about, a
        // client watching the dialog would see "something moved" and could not
        // show which row went running - which is the whole reason the dialog
        // stays open.
        $this->reportProgressTo(
            $requestedBy,
            $this->uniqueId(),
            'board-meetings',
            RunSubject::of('board-meeting', $meetingId),
        );
    }

    /**
     * Identity for uniqueness: the meeting plus what it was asked for.
     *
     * The subset is sorted so `['topics', 'key_decisions']` and
     * `['key_decisions', 'topics']` are recognised as the same request — the
     * order a client happened to send is not a different run, and treating it
     * as one would let a duplicate through the very lock meant to stop it.
     * The model is in the identity for the same reason as the mode: asking
     * twice on purpose with two different models is a comparison, and
     * dropping the second would silently answer it with the first.
     *
     * Both binding halves are in it, separated from the fields by a character
     * no name can contain. Without that separator `provider = 'a'` and
     * `model = 'bc'` would collide with `provider = 'ab'` and `model = 'c'`,
     * and the second click would be dropped as a duplicate of the first — which
     * is the one outcome this lock exists to prevent.
     */
    public function uniqueId(): string
    {
        $fields = $this->fields;

        sort($fields);

        return implode('|', [
            $this->meetingId,
            implode(',', $fields),
            $this->mode->value,
            $this->provider ?? '',
            $this->model ?? '',
            $this->merge ? 'merge' : 'fresh',
        ]);
    }

    public function handle(BoardMeetingSummarizer $summarizer, MeetingSummaryApplier $applier): void
    {
        $meeting = BoardMeeting::query()->find($this->meetingId);

        if ($meeting === null) {
            return;
        }

        $applier->applyRun($meeting, MeetingSummary::pending(), $this->mode);

        $this->reportProgress(RunState::Running);

        try {
            $run = $summarizer->summarize(
                $meeting,
                $this->fields,
                $this->provider,
                $this->model,
                $this->merge,
            );
        } catch (AiNotConfigured) {
            $this->reportProgress(RunState::Failed, [
                'reason' => MeetingSummary::REASON_NOT_CONFIGURED,
            ]);

            $run = MeetingSummary::failure(MeetingSummary::REASON_NOT_CONFIGURED);
            $applier->applyRun($meeting, $run, $this->mode);
            $this->report($meeting, $run);

            return;
        }

        $this->reportProgress(
            $run->status === MeetingSummary::STATUS_FAILED ? RunState::Failed : RunState::Finished,
            ['status' => $run->status],
        );

        $applier->applyRun($meeting, $run, $this->mode);
        $this->report($meeting, $run);
    }

    /**
     * The queue has given up on this run.
     *
     * Reached only once the attempts are exhausted, never between them, which
     * is what keeps the promise above: a run still worth retrying says nothing
     * and keeps its `pending` stamp, and only an abandoned one reports. This is
     * deliberately the *same* outcome written by `handle()`'s own failure path
     * rather than a separate "the infrastructure broke" state, because to the
     * person waiting there is one fact — the run will not produce a proposal —
     * and two states for it would mean two places to check for it.
     *
     * The reason is `unavailable` rather than `not_configured`: nothing here
     * says the hub is misconfigured, and telling an administrator to go and
     * configure a provider that was configured all along sends them looking in
     * the wrong place. It is the transient case, and the failed notification
     * says so.
     *
     * Any throwable is accepted, not a curated list. Guzzle's own
     * `ConnectException` reaches this method unwrapped for the Ollama gateway,
     * so a hand-picked set of provider exceptions would miss precisely the
     * failure this exists for — a filtered list is a list that grows a hole
     * the first time a new driver ships.
     */
    public function failed(\Throwable $e): void
    {
        $meeting = BoardMeeting::query()->find($this->meetingId);

        if ($meeting === null) {
            // The meeting was deleted while the run was in flight. There is
            // nowhere to write the outcome and nobody to point at, so this is
            // a no-op rather than a second failure inside the failure handler.
            return;
        }

        $this->reportProgress(RunState::Failed, [
            'reason' => MeetingSummary::REASON_UNAVAILABLE,
        ]);

        $run = MeetingSummary::failure(MeetingSummary::REASON_UNAVAILABLE);
        app(MeetingSummaryApplier::class)->applyRun($meeting, $run, $this->mode);
        $this->report($meeting, $run);
    }

    /**
     * The run happened; say so. Dispatched after the write rather than inside
     * it, so a rolled-back write cannot leave behind an audit row claiming a
     * run that never landed.
     *
     * The *run*, not the row it produced. This used to be handed the result of
     * `applyRun()`, which is a different thing and made the event contradict
     * its own contract ("the run is described, not the row"): under `review`
     * the applied result is the previous summary with this run attached as a
     * proposal, so its `status` was the status of whatever was there before -
     * `none` on a meeting nobody had summarised yet. The notification listener
     * reads that status to decide whether to announce a failure, so a run that
     * died under the default mode announced itself as ready to review, and the
     * rows it wrote carried `status: "none"`. Under `apply` the row is a merge
     * of this run and every earlier one, so `filled_fields` credited fields
     * from previous runs to this one.
     *
     * Writing the row is still the applier's job and still happens first; only
     * the description of what happened is taken from what actually ran.
     */
    private function report(BoardMeeting $meeting, MeetingSummary $run): void
    {
        event(new MeetingSummaryGenerated(
            $meeting,
            $run,
            $this->mode,
            $this->requestedBy,
            $this->batchId,
        ));
    }
}
