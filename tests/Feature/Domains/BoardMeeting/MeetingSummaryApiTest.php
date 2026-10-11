<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Events\MeetingSummaryAccepted;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSummarizer;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Services\MeetingSummaryCanceller;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Tagging\Services\TagService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

/*
 * The two write paths, at the boundary. Both sit on `board-meeting.update`
 * because both are writes to the meeting's record, and neither is a registry
 * operation — so a person who can curate a meeting is the person who decides
 * what a run of the model did to it.
 */

beforeEach(function () {
    seedSummaryProvider();

    $this->meeting = BoardMeeting::factory()->create(['minutes' => 'مصوبهٔ شمارهٔ ۴۱ تصویب شد.']);

    $this->run = function (array $response, SummaryWriteMode $mode = SummaryWriteMode::Review): void {
        MeetingSummaryAgent::fake([$response]);

        (new GenerateBoardMeetingSummaryJob(
            $this->meeting->getKey(),
            ['executive_summary', 'topics', 'suggested_tags'],
            null,
            null,
            $mode,
        ))->handle(app(BoardMeetingSummarizer::class), app(MeetingSummaryApplier::class), app(MeetingSummaryCanceller::class));
    };
});

describe('POST /api/board-meetings/{meeting}/summary', function () {
    it('forbids a user who cannot edit the meeting', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.view']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary")
            ->assertForbidden();

        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
    });

    it('queues a run and answers with the meeting', function () {
        Queue::fake();

        $response = actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary", ['mode' => 'review'])
            ->assertOk();

        // The run is queued, not performed: the model is the slow part by orders
        // of magnitude and the registry holds hundreds of meetings. The client
        // gets the same response either way and so needs no second code path.
        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class);
        expect($response->json('data.id'))->toBe($this->meeting->id);
    });

    it('defaults to proposing rather than applying', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary")
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->mode === SummaryWriteMode::Review,
        );
    });

    it('passes the chosen mode through', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary", ['mode' => 'apply'])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->mode === SummaryWriteMode::Apply,
        );
    });

    it('asks for every field when none are named', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary", ['fields' => []])
            ->assertOk();

        // Empty is a real instruction, not a missing value: "the whole thing"
        // needs no special spelling anywhere in the feature.
        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->fields === [],
        );
    });

    it('rejects a field outside the vocabulary', function () {
        // The keys in the stored artifact are a contract (ADR-003), so a field
        // nobody declared is a 422 here rather than a key the UI renders as an
        // empty box forever.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary", ['fields' => ['vibes']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fields.0');
    });

    it('rejects a mode it does not know', function () {
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary", ['mode' => 'whatever'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mode');
    });

    it('refuses a second run while a proposal run is under way', function () {
        Queue::fake();

        // The run reports on the candidate under `Review`, and the record's own
        // status is deliberately left alone — so a guard that read the status
        // only would wave a second run straight through the first one. Two runs
        // of one meeting race for one `summary` column, and whichever lands
        // second takes the first one's proposal with it.
        $this->meeting->forceFill(['summary' => MeetingSummary::pending()->toArray()])->save();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary")
            ->assertConflict();

        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
    });

    it('refuses a second run while an applied run has the row itself pending', function () {
        Queue::fake();

        // The same rule under `Apply`, where the run *is* the row and the
        // candidate is never written. Both placements, or one of the two modes
        // would be unguarded.
        $this->meeting->forceFill([
            'summary' => MeetingSummary::pending()->mergedOver(MeetingSummary::none())->toArray(),
        ])->save();

        expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_PENDING)
            ->and($this->meeting->fresh()->summary['pending'])->toBeNull();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary")
            ->assertConflict();

        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
    });

    it('allows a run once the previous one has reported', function () {
        Queue::fake();

        // A proposal awaiting a decision is not a run in flight, and the whole
        // point of being able to ask for another run is that the first one may
        // have got it wrong. The guard is about concurrency, not about how many
        // times somebody may ask.
        $this->meeting->forceFill([
            'summary' => MeetingSummary::none()->withPending(new MeetingSummary(
                status: MeetingSummary::STATUS_OK,
                values: ['executive_summary' => 'خلاصهٔ نادرست'],
                fields: ['executive_summary' => ['status' => MeetingSummary::FIELD_OK]],
            ))->toArray(),
        ])->save();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary")
            ->assertOk();

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class);
    });

    it('allows a run over a stamp the queue abandoned', function () {
        Queue::fake();

        // A `pending` stamp is written when a run starts and cleared when it
        // reports, so a worker killed mid-call clears nothing: the row says
        // "queued" for ever and the 409 refuses work nobody is doing. Past the
        // job's own timeout and the uniqueness lock that owns the run, the
        // stamp is not a run and the guard has to stop treating it as one.
        $stale = MeetingSummary::pending()->toArray();
        $stale['started_at'] = now()->subHours(2)->toIso8601String();

        $this->meeting->forceFill(['summary' => $stale])->save();

        $response = actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary")
            ->assertOk();

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class);

        // Answered with the cleared row, so the client reflects what the guard
        // just saw rather than the stamp it replaced.
        expect($response->json('data.summary.status'))->toBe(MeetingSummary::STATUS_FAILED)
            ->and($response->json('data.summary.error'))->toBe(MeetingSummary::REASON_ABANDONED)
            ->and($response->json('data.summary.pending'))->toBeNull();
    });
});

describe('POST /api/board-meetings/{meeting}/summary/accept', function () {
    it('forbids a user who cannot edit the meeting', function () {
        $user = createUserWithPermissions(['board-meeting.view']);

        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        actingAs($user)
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
                'accepted_fields' => ['executive_summary'],
                'tag_slugs' => [],
            ])
            ->assertForbidden();

        expect(MeetingSummary::fromArray($this->meeting->fresh()->summary)->pending)->not->toBeNull();
    });

    it('writes the accepted fields and the tag set it was given', function () {
        Event::fake([MeetingSummaryAccepted::class]);
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه'], 'suggested_tags' => ['tender']]);

        $response = actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
                'accepted_fields' => ['executive_summary', 'topics'],
                'tag_slugs' => ['budget', 'tender'],
            ])
            ->assertOk();

        $stored = MeetingSummary::fromArray($this->meeting->fresh()->summary);

        expect($stored->values['executive_summary'])->toBe('خلاصه')
            ->and($stored->values['topics'])->toBe(['بودجه'])
            ->and($stored->pending)->toBeNull()
            ->and($this->meeting->fresh()->tags->pluck('slug')->all())
            ->toEqualCanonicalizing(['budget', 'tender'])
            ->and($response->json('data.tags'))->toHaveCount(2);
    });

    it('records the decision as a person\'s, with the tag diff', function () {
        Event::fake([MeetingSummaryAccepted::class]);
        app(TagService::class)->syncTags($this->meeting, ['obsolete']);
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        $user = createUserWithPermissions(['board-meeting.update']);

        actingAs($user)->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
            'accepted_fields' => ['executive_summary'],
            'tag_slugs' => ['budget'],
        ])->assertOk();

        Event::assertDispatched(MeetingSummaryAccepted::class, function (MeetingSummaryAccepted $event): bool {
            // The old tag set has to be passed in rather than read off the
            // meeting: by now the meeting holds the new one, and an audit row
            // that only knows what is true now cannot say what it replaced.
            return $event->subject() === ['type' => 'board-meeting', 'id' => $this->meeting->id]
                && $event->metadata()['accepted_fields'] === ['executive_summary']
                && $event->metadata()['added_tags'] === ['budget']
                && $event->metadata()['removed_tags'] === ['obsolete']
                && $event->changes() === ['old' => ['tags' => ['obsolete']], 'new' => ['tags' => ['budget']]];
        });
    });

    it('leaves an audit row naming the person who decided', function () {
        // The run event is the model's act and has no human actor; this one is
        // somebody's, and the Audit domain resolves that from the request. Both
        // are asserted separately because faking the event also stops the
        // listener that writes the row.
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        $user = createUserWithPermissions(['board-meeting.update']);

        actingAs($user)->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
            'accepted_fields' => ['executive_summary'],
            'tag_slugs' => [],
        ])->assertOk();

        $row = AuditLog::query()
            ->where('event', 'boardmeeting.summary_accepted')
            ->where('subject_type', 'board-meeting')
            ->where('subject_id', $this->meeting->id)
            ->sole();

        expect($row->actor_id)->toBe($user->id)
            ->and($row->actor_type)->toBe('user')
            ->and($row->category)->toBe('boardmeeting')
            ->and($row->new_values)->toBe(['tags' => []])
            ->and($row->metadata['accepted_fields'])->toBe(['executive_summary']);
    });

    it('answers a conflict when there is nothing to decide about', function () {
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
                'accepted_fields' => ['executive_summary'],
                'tag_slugs' => [],
            ])->assertOk();

        // The meeting is there and the route is there; the thing being decided
        // about is gone, because the run was applied or somebody else got there
        // first. A 409 tells the client to re-read rather than to retry.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
                'accepted_fields' => ['executive_summary'],
                'tag_slugs' => [],
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', __('board_meeting.summary.nothing_pending'));
    });

    it('rejects a request with no tag list at all', function () {
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        // "No tags" and "leave the tags alone" are different requests. A client
        // that omits the key has made a mistake, not asked to clear the
        // meeting's tags — so the key is required rather than defaulted.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
                'accepted_fields' => ['executive_summary'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_slugs');
    });

    it('accepts an empty tag list as a decision to have no tags', function () {
        app(TagService::class)->syncTags($this->meeting, ['budget']);
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
                'accepted_fields' => [],
                'tag_slugs' => [],
            ])
            ->assertOk();

        expect($this->meeting->fresh()->tags)->toHaveCount(0);
    });

    it('rejects an accepted field outside the vocabulary', function () {
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
                'accepted_fields' => ['vibes'],
                'tag_slugs' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accepted_fields.0');
    });

    it('reads an empty accepted list as none of it, not as all of it', function () {
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/accept", [
                'accepted_fields' => [],
                'tag_slugs' => [],
            ])
            ->assertOk();

        // The generate request's `fields: []` means all of them; this one's
        // `accepted_fields: []` means none. Same vocabulary, opposite meanings,
        // which is why the keys are named differently.
        expect(MeetingSummary::fromArray($this->meeting->fresh()->summary)->status)
            ->toBe(MeetingSummary::STATUS_NONE);
    });
});

describe('POST /api/board-meetings/{meeting}/summary/dismiss', function () {
    it('forbids a user who cannot edit the meeting', function () {
        ($this->run)(['executive_summary' => 'خلاصه']);

        actingAs(createUserWithPermissions(['board-meeting.view']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss")
            ->assertForbidden();

        expect(MeetingSummary::fromArray($this->meeting->fresh()->summary)->pending)->not->toBeNull();
    });

    it('closes the proposal and leaves the record alone', function () {
        ($this->run)(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]);

        $before = MeetingSummary::fromArray($this->meeting->fresh()->summary);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss")
            ->assertOk();

        $after = MeetingSummary::fromArray($this->meeting->fresh()->summary);

        expect($after->pending)->toBeNull()
            // Everything the proposal was not is exactly what it was: refusing
            // it is not a request to blank the fields it proposed.
            ->and($after->status)->toBe($before->status)
            ->and($after->values)->toBe($before->values)
            ->and($after->fields)->toBe($before->fields);
    });

    it('cannot touch the tags, even when they changed under the panel', function () {
        // The regression this endpoint exists for. Accepting carries the tag
        // list in full, so refusing used to mean sending the tags back — and
        // that re-wrote the pivot inside the summary transaction, silently
        // reverting anybody who re-tagged the meeting between the panel
        // rendering and the click. A bodiless request cannot name tags, so
        // there is nothing to get wrong.
        ($this->run)(['executive_summary' => 'خلاصه']);
        app(TagService::class)->syncTags($this->meeting, ['budget', 'tender']);

        // Somebody re-tags the meeting while the proposal sits undecided.
        app(TagService::class)->syncTags($this->meeting, ['strategy']);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss")
            ->assertOk();

        expect($this->meeting->fresh()->tags->pluck('slug')->all())->toBe(['strategy']);
    });

    it('ignores a body rather than acting on it', function () {
        // A dismiss that honoured `accepted_fields` or `tag_slugs` would be a
        // second way to spell accept, with the tag hazard the dedicated
        // endpoint just removed. Sending them must change nothing.
        ($this->run)(['executive_summary' => 'خلاصه']);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss", [
                'accepted_fields' => ['executive_summary'],
                'tag_slugs' => ['budget'],
            ])
            ->assertOk();

        $stored = MeetingSummary::fromArray($this->meeting->fresh()->summary);

        expect($stored->pending)->toBeNull()
            ->and($stored->values['executive_summary'] ?? null)->toBeNull()
            ->and($this->meeting->fresh()->tags)->toHaveCount(0);
    });

    it('records a refusal as its own act, not as an acceptance of nothing', function () {
        ($this->run)(['executive_summary' => 'خلاصه']);

        $user = createUserWithPermissions(['board-meeting.update']);

        actingAs($user)->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss")
            ->assertOk();

        // A rejection and "accepted nothing while setting a tag list" are
        // different facts, and an audit trail that records both as
        // `summary_accepted` cannot tell them apart. The refusal also records
        // no diff: nothing about the record changed.
        $row = AuditLog::query()
            ->where('event', 'boardmeeting.summary_dismissed')
            ->where('subject_type', 'board-meeting')
            ->where('subject_id', $this->meeting->id)
            ->sole();

        expect($row->actor_id)->toBe($user->id)
            ->and($row->category)->toBe('boardmeeting')
            ->and($row->old_values)->toBeNull()
            ->and($row->new_values)->toBeNull()
            // The run's own status, not a word about the person: this helper
            // asks for three fields and the fake answered one, so the run is
            // `partial` - and that is exactly the fact worth keeping.
            ->and($row->metadata['run_status'])->toBe(MeetingSummary::STATUS_PARTIAL);

        expect(AuditLog::query()->where('event', 'boardmeeting.summary_accepted')->exists())->toBeFalse();
    });

    it('names the run status, so an unusable run is distinguishable from a read one', function () {
        // "Refused because the model returned nothing" and "refused after
        // reading it" are different facts about how the feature is working,
        // and only the first is a signal that something is wrong.
        $this->meeting->forceFill(['summary' => null])->save();
        MeetingSummaryAgent::fake([[]]);

        (new GenerateBoardMeetingSummaryJob(
            $this->meeting->getKey(),
            ['executive_summary'],
            null,
            null,
            SummaryWriteMode::Review,
        ))->handle(app(BoardMeetingSummarizer::class), app(MeetingSummaryApplier::class), app(MeetingSummaryCanceller::class));

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss")
            ->assertOk();

        expect(AuditLog::query()
            ->where('event', 'boardmeeting.summary_dismissed')
            ->sole()
            ->metadata['run_status'])->toBe(MeetingSummary::STATUS_FAILED);
    });

    it('answers a conflict when there is nothing to refuse', function () {
        ($this->run)(['executive_summary' => 'خلاصه'], SummaryWriteMode::Apply);

        // Applied, so nothing awaits a decision. The same 409 the accept path
        // raises: the meeting and route are there, the thing being decided
        // about is gone.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss")
            ->assertStatus(409);
    });

    it('answers a conflict on a second refusal', function () {
        ($this->run)(['executive_summary' => 'خلاصه']);

        $user = createUserWithPermissions(['board-meeting.update']);

        actingAs($user)->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss")->assertOk();
        actingAs($user)->postJson("/api/board-meetings/{$this->meeting->id}/summary/dismiss")->assertStatus(409);
    });
});
