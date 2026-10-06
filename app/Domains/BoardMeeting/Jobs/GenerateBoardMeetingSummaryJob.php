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
 * rejected key, a timeout) propagates so the queue retries it, and the
 * meeting keeps its `pending` stamp until a run does report. Which also means
 * a run that never reports leaves no audit row: D22 asks for an event per
 * *run*, and a run that is still being retried has not finished.
 */
class GenerateBoardMeetingSummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsRunProgress;
    use SerializesModels;

    /** Release the lock in case the worker dies holding it. */
    public int $uniqueFor = 900;

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
     */
    public function __construct(
        public readonly int $meetingId,
        public readonly array $fields = [],
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly SummaryWriteMode $mode = SummaryWriteMode::Review,
        public readonly bool $merge = false,
        public readonly ?int $requestedBy = null,
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
        $this->reportProgressTo(
            $requestedBy,
            $this->uniqueId(),
            'board-meetings',
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

            $this->report($meeting, $applier->applyRun(
                $meeting,
                MeetingSummary::failure(MeetingSummary::REASON_NOT_CONFIGURED),
                $this->mode,
            ));

            return;
        }

        $this->reportProgress(
            $run->status === MeetingSummary::STATUS_FAILED ? RunState::Failed : RunState::Finished,
            ['status' => $run->status],
        );

        $this->report($meeting, $applier->applyRun($meeting, $run, $this->mode));
    }

    /**
     * The run happened; say so. Dispatched after the write rather than inside
     * it, so a rolled-back write cannot leave behind an audit row claiming a
     * run that never landed.
     */
    private function report(BoardMeeting $meeting, MeetingSummary $run): void
    {
        event(new MeetingSummaryGenerated($meeting, $run, $this->mode));
    }
}
