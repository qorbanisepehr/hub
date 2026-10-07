<?php

use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Events\MeetingSummaryGenerated;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryBatchFinishedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryFailedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryReadyNotification;
use App\Domains\BoardMeeting\Services\MeetingSummaryBatchTracker;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Notification\Listeners\NotifySummaryRequesterOfRun;
use App\Domains\Notification\Support\NotificationType;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/*
 * What a finished run tells the person who asked for it.
 *
 * The listener is one decision with three outcomes - ready, failed, or one
 * message for a whole bulk - and each of those is a promise about what the
 * reader will find if they act on it. These are the tests for those promises,
 * not for the plumbing: the plumbing (that an event reaches a listener) is
 * asserted once, and everything after it is about *who* is told and *what*.
 */

/**
 * A run that produced something worth reviewing.
 *
 * Built rather than faked through the agent because what is under test is the
 * announcement of an outcome, not how the outcome was reached.
 */
function summaryRunWorthReviewing(): MeetingSummary
{
    return new MeetingSummary(
        status: MeetingSummary::STATUS_OK,
        values: ['executive_summary' => 'خلاصهٔ جلسه'],
        fields: ['executive_summary' => ['status' => MeetingSummary::FIELD_OK]],
    );
}

/**
 * A run the queue abandoned, as the job reports it after the last attempt.
 */
function summaryRunAbandoned(): MeetingSummary
{
    return MeetingSummary::failure(MeetingSummary::REASON_UNAVAILABLE);
}

/**
 * One finished run, as the job dispatches it.
 */
function summaryRunAnnounced(
    BoardMeeting $meeting,
    MeetingSummary $run,
    ?int $requestedBy,
    ?string $batchId = null,
): MeetingSummaryGenerated {
    return new MeetingSummaryGenerated(
        $meeting,
        $run,
        SummaryWriteMode::Review,
        $requestedBy,
        $batchId,
    );
}

describe('boardmeeting summary notifications', function () {
    beforeEach(function () {
        $this->meeting = BoardMeeting::factory()->create([
            'title' => 'جلسهٔ شورا',
            'minutes' => 'مصوبهٔ شمارهٔ ۴۱ تصویب شد.',
        ]);
    });

    it('registers the listener against the run-finished event', function () {
        Event::fake();

        Event::assertListening(MeetingSummaryGenerated::class, NotifySummaryRequesterOfRun::class);
    });

    it('defers the listener until after the database transaction commits', function () {
        // A run whose write rolls back must not leave a notification claiming
        // something that did not happen.
        expect(app(NotifySummaryRequesterOfRun::class))
            ->toBeInstanceOf(ShouldHandleEventsAfterCommit::class);
    });

    it('tells only the person who asked', function () {
        Notification::fake();

        $requester = createUserWithPermissions(['board-meeting.view']);
        $bystander = createUserWithPermissions(['board-meeting.view']);

        app(NotifySummaryRequesterOfRun::class)->handle(
            summaryRunAnnounced($this->meeting, summaryRunWorthReviewing(), $requester->id),
        );

        Notification::assertSentTo($requester, BoardmeetingSummaryReadyNotification::class);
        Notification::assertNotSentTo($bystander, BoardmeetingSummaryReadyNotification::class);
    });

    it('points at the meeting rather than carrying the summary', function () {
        // A notification row outlives the proposal it announces, so copying the
        // content into it would create a second copy that can drift from the
        // column it came from.
        Notification::fake();

        $requester = createUserWithPermissions(['board-meeting.view']);

        app(NotifySummaryRequesterOfRun::class)->handle(
            summaryRunAnnounced($this->meeting, summaryRunWorthReviewing(), $requester->id),
        );

        Notification::assertSentTo(
            $requester,
            BoardmeetingSummaryReadyNotification::class,
            function (BoardmeetingSummaryReadyNotification $notification, array $channels) use ($requester): bool {
                expect($channels)->toBe(['database', 'broadcast']);
                expect($notification->toDatabase($requester))->toBe([
                    'meeting_id' => $this->meeting->id,
                    'status' => MeetingSummary::STATUS_OK,
                    'resource' => [
                        'type' => 'board-meeting',
                        'id' => (string) $this->meeting->id,
                        'action' => 'review-summary',
                    ],
                ]);

                return true;
            },
        );
    });

    it('offers review only when the run actually left something', function () {
        // The distinction the reader cannot make for themselves: "come and
        // decide" and "this did not work" need opposite reactions, and a
        // notification that invites review of a dead run sends them looking for
        // a proposal that is not there.
        Notification::fake();

        $requester = createUserWithPermissions(['board-meeting.view']);

        app(NotifySummaryRequesterOfRun::class)->handle(
            summaryRunAnnounced($this->meeting, summaryRunAbandoned(), $requester->id),
        );

        Notification::assertSentTo($requester, BoardmeetingSummaryFailedNotification::class);
        Notification::assertNotSentTo($requester, BoardmeetingSummaryReadyNotification::class);
    });

    it('carries why the run failed, not merely that it did', function () {
        // Not-configured is an administrator's task; unavailable is a retry.
        // "It failed" cannot tell them apart, and the reader can do nothing
        // with the weaker version.
        Notification::fake();

        $requester = createUserWithPermissions(['board-meeting.view']);

        app(NotifySummaryRequesterOfRun::class)->handle(
            summaryRunAnnounced($this->meeting, summaryRunAbandoned(), $requester->id),
        );

        Notification::assertSentTo(
            $requester,
            BoardmeetingSummaryFailedNotification::class,
            function (BoardmeetingSummaryFailedNotification $notification, array $channels) use ($requester): bool {
                expect($notification->toDatabase($requester))->toMatchArray([
                    'meeting_id' => $this->meeting->id,
                    'reason' => MeetingSummary::REASON_UNAVAILABLE,
                ]);

                return true;
            },
        );
    });

    it('tells nobody for a run that had nobody behind it', function () {
        // Scheduled and CLI runs have no requester. Inventing one would put a
        // message about somebody else's meeting in a stranger's inbox.
        Notification::fake();

        app(NotifySummaryRequesterOfRun::class)->handle(
            summaryRunAnnounced($this->meeting, summaryRunWorthReviewing(), null),
        );

        Notification::assertNothingSent();
    });

    it('ignores a requester who was deleted while the run was in flight', function () {
        // A missing recipient must be a no-op, not a failure that retries the
        // whole run.
        Notification::fake();

        app(NotifySummaryRequesterOfRun::class)->handle(
            summaryRunAnnounced($this->meeting, summaryRunWorthReviewing(), 999999),
        );

        Notification::assertNothingSent();
    });

    it('speaks a type the rest of the board-meeting surfaces already use', function () {
        // The class name is spelled with a lowercase m precisely so this holds:
        // the canonical type is derived from the class name by splitting on the
        // first underscore, and `BoardMeeting...` would yield `board.meeting_...`,
        // a domain prefix that disagrees with the audit catalog, `resource.type`
        // and the permissions.
        expect(NotificationType::canonical(BoardmeetingSummaryReadyNotification::class))
            ->toBe('boardmeeting.summary_ready');

        expect(NotificationType::canonical(BoardmeetingSummaryFailedNotification::class))
            ->toBe('boardmeeting.summary_failed');

        expect(NotificationType::canonical(BoardmeetingSummaryBatchFinishedNotification::class))
            ->toBe('boardmeeting.summary_batch_finished');

        // Without the translation keys the notification centre would render a
        // title of null for every one of them.
        expect(NotificationType::translated('boardmeeting.summary_ready', 'title'))->not->toBeNull();
        expect(NotificationType::translated('boardmeeting.summary_failed', 'title'))->not->toBeNull();
        expect(NotificationType::translated('boardmeeting.summary_batch_finished', 'title'))->not->toBeNull();
    });

    describe('a bulk dispatch', function () {
        it('announces itself once, when the last run reports', function () {
            // Per-meeting notifications for a bulk are not a smaller feature
            // but a broken one: two hundred rows inform nobody and teach people
            // to ignore the channel, which costs the single-run notifications
            // their entire value.
            Notification::fake();

            $requester = createUserWithPermissions(['board-meeting.view']);
            $tracker = app(MeetingSummaryBatchTracker::class);
            $tracker->start('batch-7', 2);

            app(NotifySummaryRequesterOfRun::class)->handle(
                summaryRunAnnounced($this->meeting, summaryRunWorthReviewing(), $requester->id, 'batch-7'),
            );

            Notification::assertNothingSent();

            app(NotifySummaryRequesterOfRun::class)->handle(
                summaryRunAnnounced($this->meeting, summaryRunWorthReviewing(), $requester->id, 'batch-7'),
            );

            Notification::assertSentToTimes($requester, BoardmeetingSummaryBatchFinishedNotification::class, 1);
        });

        it('says how the batch went rather than only that it is over', function () {
            // "Your forty runs finished" is not actionable; "thirty-eight
            // produced summaries, two did not" is.
            Notification::fake();

            $requester = createUserWithPermissions(['board-meeting.view']);
            $tracker = app(MeetingSummaryBatchTracker::class);
            $tracker->start('batch-8', 2);

            app(NotifySummaryRequesterOfRun::class)->handle(
                summaryRunAnnounced($this->meeting, summaryRunWorthReviewing(), $requester->id, 'batch-8'),
            );

            app(NotifySummaryRequesterOfRun::class)->handle(
                summaryRunAnnounced($this->meeting, summaryRunAbandoned(), $requester->id, 'batch-8'),
            );

            Notification::assertSentTo(
                $requester,
                BoardmeetingSummaryBatchFinishedNotification::class,
                function (BoardmeetingSummaryBatchFinishedNotification $notification, array $channels) use ($requester): bool {
                    expect($notification->toDatabase($requester))->toBe([
                        'succeeded' => 1,
                        'failed' => 1,
                        'total' => 2,
                        // There is no single meeting a batch is about, so the
                        // client falls back to the notification centre rather
                        // than being sent to an arbitrary row.
                        'resource' => null,
                    ]);

                    return true;
                },
            );
        });

        it('keeps a run inside a batch from also announcing itself', function () {
            // The batch is the message for a bulk. A per-meeting notification
            // beside it would be the firehose the batch exists to avoid.
            Notification::fake();

            $requester = createUserWithPermissions(['board-meeting.view']);
            $tracker = app(MeetingSummaryBatchTracker::class);
            $tracker->start('batch-9', 1);

            app(NotifySummaryRequesterOfRun::class)->handle(
                summaryRunAnnounced($this->meeting, summaryRunAbandoned(), $requester->id, 'batch-9'),
            );

            Notification::assertNotSentTo($requester, BoardmeetingSummaryFailedNotification::class);
            Notification::assertNotSentTo($requester, BoardmeetingSummaryReadyNotification::class);
            Notification::assertSentTo($requester, BoardmeetingSummaryBatchFinishedNotification::class);
        });
    });
});
