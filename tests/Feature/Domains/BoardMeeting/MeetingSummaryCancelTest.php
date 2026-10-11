<?php

use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Events\MeetingSummaryGenerated;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSummarizer;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Services\MeetingSummaryBatchTracker;
use App\Domains\BoardMeeting\Services\MeetingSummaryCanceller;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Tagging\Services\TagService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

/*
 * Stopping a summary run (idea 728).
 *
 * A model call in flight cannot be aborted from another process, so stopping is
 * cooperative and split by where the run is: a queued run is skipped by a cache
 * marker before the model is ever asked, and a run that is already answering is
 * let go by clearing its stamp and having the run itself refuse to write on the
 * way back. These are the tests for both halves, and for the one thing a stop is
 * deliberately *not*: a second way to dismiss an answered proposal.
 */

beforeEach(function () {
    seedSummaryProvider();

    $this->meeting = BoardMeeting::factory()->create(['minutes' => 'مصوبهٔ شمارهٔ ۴۱ تصویب شد.']);

    // A meeting the «تولید برای تولیدنشده‌ها» scope will queue: never run. A
    // `none` row is deliberately *not* eligible — it holds a proposal nobody
    // has decided — so a batch test needs a genuinely fresh meeting.
    $this->eligible = fn (): BoardMeeting => BoardMeeting::factory()->create(['minutes' => 'صورتجلسه']);
});

/**
 * The stamp a `Review`-mode run leaves: the record untouched, the run on
 * `pending`.
 */
function cancelReviewStamp(): array
{
    return MeetingSummary::none()->withPending(MeetingSummary::pending())->toArray();
}

/**
 * The stamp an `Apply`-mode run leaves: the run *is* the row.
 */
function cancelApplyStamp(): array
{
    return MeetingSummary::pending()->mergedOver(MeetingSummary::none())->toArray();
}

/**
 * One run the applier can write, used by the override summarizer below.
 */
function cancelRunResult(): MeetingSummary
{
    return new MeetingSummary(
        status: MeetingSummary::STATUS_OK,
        values: ['executive_summary' => 'پیش‌نویس'],
        fields: ['executive_summary' => ['status' => MeetingSummary::FIELD_OK]],
    );
}

describe('POST /api/board-meetings/{meeting}/summary/cancel', function () {
    it('forbids a user who cannot edit the meeting', function () {
        $this->meeting->forceFill(['summary' => cancelReviewStamp()])->save();

        actingAs(createUserWithPermissions(['board-meeting.view']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/cancel")
            ->assertForbidden();

        expect(MeetingSummary::fromArray($this->meeting->fresh()->summary)->isInFlight())->toBeTrue();
    });

    it('clears a proposal-mode run without touching the record', function () {
        $this->meeting->forceFill(['summary' => cancelReviewStamp()])->save();

        $response = actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/cancel")
            ->assertOk();

        // The record itself was never written by a `Review` run, so stopping it
        // restores exactly what was there before: `none`.
        expect($response->json('data.summary.status'))->toBe(MeetingSummary::STATUS_NONE)
            ->and($response->json('data.summary.pending'))->toBeNull()
            ->and(MeetingSummary::fromArray($this->meeting->fresh()->summary)->isInFlight())->toBeFalse();
    });

    it('clears a run that had taken the row itself', function () {
        $this->meeting->forceFill(['summary' => cancelApplyStamp()])->save();

        $response = actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/cancel")
            ->assertOk();

        // Under `Apply` the stamp *is* the row, so clearing it leaves nothing
        // usable behind and the row goes back to `none` - not to a failure, and
        // not to a `pending` that would lie about a run nobody is doing.
        expect($response->json('data.summary.status'))->toBe(MeetingSummary::STATUS_NONE)
            ->and($response->json('data.summary.started_at'))->toBeNull();
    });

    it('conflicts when there is nothing running', function () {
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/cancel")
            ->assertConflict();
    });

    it('refuses to stop a proposal that has already reported', function () {
        // Once the run has answered, the question changed. "I do not want this"
        // is the dismiss path's job, and offering a stop here would be a second
        // way to spell it that also threw the proposal away.
        $this->meeting->forceFill([
            'summary' => MeetingSummary::none()->withPending(cancelRunResult())->toArray(),
        ])->save();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/cancel")
            ->assertConflict();

        expect(MeetingSummary::fromArray($this->meeting->fresh()->summary)->pending)->not->toBeNull();
    });

    it('lets a stopped meeting be asked again', function () {
        // The marker means "skip the run already queued", not "this meeting is
        // closed" - so a fresh request clears it and the new run happens.
        Queue::fake();

        $this->meeting->forceFill(['summary' => cancelReviewStamp()])->save();

        $user = createUserWithPermissions(['board-meeting.update']);

        actingAs($user)
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/cancel")
            ->assertOk();

        actingAs($user)
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary")
            ->assertOk();

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class, 1);
        expect(app(MeetingSummaryCanceller::class)->isCancelled($this->meeting->id))->toBeFalse();
    });
});

describe('a stopped run', function () {
    it('never asks the model when it is stopped while it waits', function () {
        Event::fake([MeetingSummaryGenerated::class]);
        MeetingSummaryAgent::fake();

        $this->meeting->forceFill(['summary' => cancelReviewStamp()])->save();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/cancel")
            ->assertOk();

        (new GenerateBoardMeetingSummaryJob($this->meeting->getKey(), []))->handle(
            app(BoardMeetingSummarizer::class),
            app(MeetingSummaryApplier::class),
            app(MeetingSummaryCanceller::class),
        );

        // The whole point: the slow, expensive part never happened.
        MeetingSummaryAgent::assertNeverPrompted();
        expect(MeetingSummary::fromArray($this->meeting->fresh()->summary)->isInFlight())->toBeFalse();

        Event::assertDispatched(
            MeetingSummaryGenerated::class,
            fn (MeetingSummaryGenerated $event): bool => $event->metadata()['status'] === MeetingSummary::STATUS_CANCELLED,
        );
    });

    it('discards an answer that arrives after the run was stopped', function () {
        Event::fake([MeetingSummaryGenerated::class]);

        // A summarizer that behaves like a run somebody stopped mid-call: it
        // clears its own stamp, then hands the job an answer anyway.
        $summarizer = new class(app(AiAgentRunner::class), app(TagService::class)) extends BoardMeetingSummarizer
        {
            public function summarize(
                BoardMeeting $meeting,
                array $fields = [],
                ?string $provider = null,
                ?string $model = null,
                bool $merge = false,
            ): MeetingSummary {
                app(MeetingSummaryCanceller::class)->cancelRun($meeting->fresh());

                return cancelRunResult();
            }
        };

        (new GenerateBoardMeetingSummaryJob($this->meeting->getKey(), []))->handle(
            $summarizer,
            app(MeetingSummaryApplier::class),
            app(MeetingSummaryCanceller::class),
        );

        // Nothing landed: the row the cancel restored is still the row.
        $stored = MeetingSummary::fromArray($this->meeting->fresh()->summary);

        expect($stored?->isInFlight())->toBeFalse()
            ->and($stored?->values)->toBe([]);

        Event::assertDispatched(
            MeetingSummaryGenerated::class,
            fn (MeetingSummaryGenerated $event): bool => $event->metadata()['status'] === MeetingSummary::STATUS_CANCELLED,
        );
    });

    it('writes nothing when the queue gives up on it', function () {
        $this->meeting->forceFill(['summary' => cancelReviewStamp()])->save();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/cancel")
            ->assertOk();

        (new GenerateBoardMeetingSummaryJob($this->meeting->getKey(), []))->failed(new RuntimeException('worker died'));

        // `failed` is where a run reports `unavailable`; a stopped run must not,
        // or the person who called it off would be told the model broke.
        $stored = MeetingSummary::fromArray($this->meeting->fresh()->summary);

        expect($stored?->error)->not->toBe(MeetingSummary::REASON_UNAVAILABLE)
            ->and($stored?->isInFlight())->toBeFalse();
    });
});

describe('POST /api/board-meetings/summary/batches/{batchId}/cancel', function () {
    it('forbids a user who cannot sync the registry', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson('/api/board-meetings/summary/batches/anything/cancel')
            ->assertForbidden();
    });

    it('stops the runs still outstanding and forgets the tally', function () {
        Queue::fake();

        $first = ($this->eligible)();
        $second = ($this->eligible)();

        $bulk = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$first->id, $second->id]])
            ->assertOk();

        $batchId = $bulk->json('data.batch_id');
        $tracker = app(MeetingSummaryBatchTracker::class);

        expect($tracker->meetingIds($batchId))->toEqualCanonicalizing([$first->id, $second->id]);

        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson("/api/board-meetings/summary/batches/{$batchId}/cancel")
            ->assertOk();

        // Both numbers are the point: the runs are stopped, and the batch has
        // no tally left to announce a completion nobody is waiting for.
        expect($response->json('data.stopped'))->toBe(2)
            ->and($tracker->meetingIds($batchId))->toBe([])
            ->and($tracker->complete($batchId, true))->toBeNull();
    });

    it('frees a run that is already in flight', function () {
        Queue::fake();

        $first = ($this->eligible)();
        $second = ($this->eligible)();

        $batchId = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$first->id, $second->id]])
            ->assertOk()
            ->json('data.batch_id');

        // As if the first run's job had started while the batch was still going.
        $first->forceFill(['summary' => cancelApplyStamp()])->save();

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson("/api/board-meetings/summary/batches/{$batchId}/cancel")
            ->assertOk();

        expect(MeetingSummary::fromArray($first->fresh()->summary)?->isInFlight())->toBeFalse();
    });

    it('stands a queued run down rather than paying for it', function () {
        Queue::fake();

        $meeting = ($this->eligible)();

        $batchId = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$meeting->id]])
            ->assertOk()
            ->json('data.batch_id');

        $user = createUserWithPermissions(['board-meeting.sync']);

        actingAs($user)
            ->postJson("/api/board-meetings/summary/batches/{$batchId}/cancel")
            ->assertOk();

        MeetingSummaryAgent::fake();

        // The job the dispatch queued, run now that the batch was stopped.
        (new GenerateBoardMeetingSummaryJob($meeting->getKey(), [], batchId: $batchId))->handle(
            app(BoardMeetingSummarizer::class),
            app(MeetingSummaryApplier::class),
            app(MeetingSummaryCanceller::class),
        );

        MeetingSummaryAgent::assertNeverPrompted();
    });

    it('conflicts when the batch is unknown', function () {
        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/batches/nobody-knows/cancel')
            ->assertConflict();
    });
});
