<?php

use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

/*
 * The bulk arm (D23) and the flat model list a per-run picker reads.
 *
 * The bulk rule is the interesting part: «تولید برای همهٔ تولیدنشده‌ها» has to
 * be true of the *write*, not just of the list the dialog rendered, because the
 * two are minutes apart and a person can generate a meeting in between.
 */

beforeEach(function () {
    $this->provider = seedSummaryProvider();

    $this->generate = function (array $attributes = []): BoardMeeting {
        return BoardMeeting::factory()->create($attributes + ['minutes' => 'مصوبهٔ ۴۱ تصویب شد.']);
    };

    $this->withSummary = function (array $summary): BoardMeeting {
        return BoardMeeting::factory()->create([
            'minutes' => 'صورتجلسه',
            'summary' => $summary,
        ]);
    };
});

describe('GET /api/ai/models', function () {
    it('forbids a user who cannot read the registry', function () {
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->getJson('/api/ai/models')
            ->assertForbidden();
    });

    it('lists pickable models across providers, flat and named', function () {
        seedSummaryProvider('gateway', makeDefault: false);
        seedSummaryModel('local', 'llama3.2');
        seedSummaryModel('gateway', 'gpt-4o-mini');

        $rows = collect(actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/models')
            ->assertOk()
            ->json('data'))
            ->keyBy('model_name');

        // Flat, and carrying the provider: a dropdown of names cannot let
        // somebody pick between two gateways that both offer `gpt-4o-mini`.
        expect($rows)->toHaveCount(2)
            ->and($rows['llama3.2']['provider_name'])->toBe('local')
            ->and($rows['gpt-4o-mini']['provider_name'])->toBe('gateway')
            ->and($rows['llama3.2']['display_label'])->toBe('llama3.2');
    });

    it('omits a model nobody can pick', function () {
        seedSummaryModel('local', 'live');
        seedSummaryModel('local', 'retired', isActive: false);
        seedSummaryProvider('dead', isActive: false, makeDefault: false);
        seedSummaryModel('dead', 'unreachable');

        $names = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/models')
            ->json('data.*.model_name');

        // The list has to match what the resolver will accept. Offering a row
        // the resolver then refuses is a control that lies about what it does.
        expect($names)->toBe(['live']);
    });

    it('never carries the provider row it does not need', function () {
        seedSummaryModel('local', 'llama3.2');

        $model = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/models')
            ->json('data.0');

        expect($model)->not->toHaveKeys(['base_url', 'has_api_key', 'headers', 'api_key']);
    });
});

describe('GET /api/board-meetings/summary/candidates', function () {
    it('forbids a user who cannot sync the registry', function () {
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->getJson('/api/board-meetings/summary/candidates')
            ->assertForbidden();
    });

    it('lists only the meetings with nothing to show', function () {
        $blank = ($this->generate)();
        $failed = ($this->withSummary)(['status' => MeetingSummary::STATUS_FAILED]);
        ($this->withSummary)(['status' => MeetingSummary::STATUS_OK, 'executive_summary' => 'خلاصه']);
        ($this->withSummary)(['status' => MeetingSummary::STATUS_PENDING]);
        ($this->withSummary)(['status' => MeetingSummary::STATUS_NONE]);

        $ids = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates')
            ->json('data.*.id');

        // A `pending` run is in flight and a `none` row is a proposal somebody
        // still has to decide about; both are a meeting somebody else is
        // already dealing with. A `failed` one has nothing to show either way.
        expect($ids)->toEqualCanonicalizing([$blank->id, $failed->id]);
    });

    it('omits a meeting whose run is under way even though its last run failed', function () {
        $stillWaiting = ($this->withSummary)(['status' => MeetingSummary::STATUS_FAILED]);
        $underWay = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_FAILED,
            'error' => MeetingSummary::REASON_UNAVAILABLE,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);

        $ids = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates')
            ->json('data.*.id');

        // Under `Review` a run leaves the record's own status alone, so a
        // meeting whose *last* run failed still reads `failed` while a new one
        // is going. Without the second condition the dialog would offer it,
        // queue a second model call, and the first one's proposal would be
        // taken by whichever landed later.
        expect($ids)->toEqualCanonicalizing([$stillWaiting->id])
            ->and($ids)->not->toContain($underWay->id);
    });

    it('carries the list filters, so the dialog can be narrowed', function () {
        $inRange = ($this->generate)(['held_on' => '2026-03-01', 'held_on_jalali' => '1405-01-10', 'sequence' => 1]);
        ($this->generate)(['held_on' => '2024-01-01', 'held_on_jalali' => '1402-10-22', 'sequence' => 3]);

        $ids = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates?date_from=2026-01-01')
            ->json('data.*.id');

        // "Generate the ones I am looking at" is the difference between a
        // hundred queued jobs and two, and it only works if the dialog reads
        // the same scope the list does.
        expect($ids)->toBe([$inRange->id]);
    });

    it('identifies a meeting the way the list does', function () {
        $meeting = ($this->generate)(['held_on' => '2026-03-01', 'held_on_jalali' => '1405-01-10', 'sequence' => 1]);

        $row = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates')
            ->json('data.0');

        expect($row['id'])->toBe($meeting->id)
            ->and($row['registry_label'])->toBe($meeting->registryLabel())
            // Not the full meeting payload: this list is a checklist, and
            // hundreds of meetings of minutes would be a lot to download to
            // render a few checkboxes.
            ->and($row)->not->toHaveKey('minutes');
    });

    it('marks each scope list with the kind of run it offers', function () {
        ($this->generate)();
        ($this->withSummary)(['status' => MeetingSummary::STATUS_OK]);

        $user = createUserWithPermissions(['board-meeting.sync']);

        $ungenerated = collect(
            actingAs($user)->getJson('/api/board-meetings/summary/candidates')->json('data'),
        )->pluck('action')->unique()->values()->all();

        $regeneratable = collect(
            actingAs($user)->getJson('/api/board-meetings/summary/candidates?regenerate=1')->json('data'),
        )->pluck('action')->unique()->values()->all();

        // Stated rather than left to the client to infer from a missing
        // summary: the list already knows, and a picker that re-derived it from
        // what it can see would decide a second time and differently.
        expect($ungenerated)->toBe(['generate'])
            ->and($regeneratable)->toBe(['regenerate']);
    });

    it('classifies an explicit id set instead of filtering it by scope', function () {
        $blank = ($this->generate)();
        $failed = ($this->withSummary)(['status' => MeetingSummary::STATUS_FAILED]);
        $ok = ($this->withSummary)(['status' => MeetingSummary::STATUS_OK]);
        $partial = ($this->withSummary)(['status' => MeetingSummary::STATUS_PARTIAL]);
        $none = ($this->withSummary)(['status' => MeetingSummary::STATUS_NONE]);
        $busy = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_PARTIAL,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);
        $unpicked = ($this->generate)();

        $ids = [$blank->id, $failed->id, $ok->id, $partial->id, $none->id, $busy->id];

        $rows = collect(actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates?'.http_build_query(['meeting_ids' => $ids]))
            ->json('data'))
            ->keyBy('id');

        // The row picker has ticks whose summaries it cannot know: a row ticked
        // on an earlier page is not in the list window, and the registry list
        // carries no summary status. So it asks about the set it has instead of
        // filtering by a scope, and a ticked row that cannot run — mid-run, or
        // holding a proposal nobody has decided — is labelled rather than
        // dropped from under the person's cursor.
        expect($rows)->toHaveCount(6)
            ->and($rows[$blank->id]['action'])->toBe('generate')
            ->and($rows[$failed->id]['action'])->toBe('generate')
            ->and($rows[$ok->id]['action'])->toBe('regenerate')
            ->and($rows[$partial->id]['action'])->toBe('regenerate')
            ->and($rows[$none->id]['action'])->toBe('blocked')
            ->and($rows[$busy->id]['action'])->toBe('blocked')
            ->and($rows)->not->toHaveKey($unpicked->id);
    });

    it('returns an explicit set whole, however small the page', function () {
        $meetings = collect(range(1, 25))->map(fn () => ($this->generate)());

        $ids = $meetings->pluck('id')->all();

        $rows = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates?'.http_build_query(['meeting_ids' => $ids]))
            ->json('data');

        // Paging the answer would answer "which of these have a summary" for
        // only the first slice of the question, and the picker would submit a
        // set it never classified.
        expect($rows)->toHaveCount(25);
    });

    it('agrees with the scope lists about which rows are which', function () {
        // The scopes filter in SQL and the explicit set is classified in PHP:
        // two spellings of one rule, driven here over the same rows, because a
        // picker that offers a row the dispatch then refuses is the bug this
        // whole check exists to prevent.
        ($this->generate)();
        ($this->withSummary)(['status' => MeetingSummary::STATUS_FAILED]);
        ($this->withSummary)(['status' => MeetingSummary::STATUS_OK]);
        ($this->withSummary)(['status' => MeetingSummary::STATUS_PARTIAL]);
        ($this->withSummary)(['status' => MeetingSummary::STATUS_NONE]);
        ($this->withSummary)([
            'status' => MeetingSummary::STATUS_PARTIAL,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);
        ($this->withSummary)([
            'status' => MeetingSummary::STATUS_FAILED,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);

        $user = createUserWithPermissions(['board-meeting.sync']);
        $all = BoardMeeting::query()->pluck('id')->all();

        $classified = fn (string $action): array => collect(
            actingAs($user)
                ->getJson('/api/board-meetings/summary/candidates?'.http_build_query(['meeting_ids' => $all]))
                ->json('data'),
        )->where('action', $action)->pluck('id')->sort()->values()->all();

        $ungenerated = collect(
            actingAs($user)->getJson('/api/board-meetings/summary/candidates')->json('data.*.id'),
        )->sort()->values()->all();

        $regeneratable = collect(
            actingAs($user)->getJson('/api/board-meetings/summary/candidates?regenerate=1')->json('data.*.id'),
        )->sort()->values()->all();

        expect($ungenerated)->toBe($classified('generate'))
            ->and($regeneratable)->toBe($classified('regenerate'));
    });
});

describe('POST /api/board-meetings/summary/bulk', function () {
    it('forbids a user who cannot sync the registry', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [1]])
            ->assertForbidden();

        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
    });

    it('queues one run per chosen meeting', function () {
        Queue::fake();
        $first = ($this->generate)();
        $second = ($this->generate)();

        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$first->id, $second->id]])
            ->assertOk();

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class, 2);
        expect($response->json('data.queued'))->toBe(2)
            ->and($response->json('data.skipped'))->toBe(0)
            ->and($response->json('data.meeting_ids'))->toEqualCanonicalizing([$first->id, $second->id]);
    });

    it('gives the whole dispatch one batch identity', function () {
        // The batch id is what lets two hundred runs be announced once instead
        // of two hundred times, and what lets the client group the live progress
        // events it is about to receive. Every job in one dispatch carries the
        // same value - and a second press produces a different one, or the
        // second dispatch would silently join the first batch and be announced
        // as part of it.
        Queue::fake();
        $first = ($this->generate)();
        $second = ($this->generate)();

        $user = createUserWithPermissions(['board-meeting.sync']);

        $one = actingAs($user)->postJson('/api/board-meetings/summary/bulk', [
            'meeting_ids' => [$first->id, $second->id],
        ])->assertOk();

        $batchId = $one->json('data.batch_id');

        expect($batchId)->toBeString()->not->toBeEmpty();

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class, fn (GenerateBoardMeetingSummaryJob $job): bool => $job->batchId === $batchId
            && $job->requestedBy === $user->id);

        $two = actingAs($user)->postJson('/api/board-meetings/summary/bulk', [
            'meeting_ids' => [$first->id],
        ])->assertOk();

        expect($two->json('data.batch_id'))->not->toBe($batchId);
    });

    it('gives no batch to a dispatch that queued nothing', function () {
        // A batch with no runs in it would be a counter nobody ever decrements,
        // and a client grouping by it would wait for events that cannot arrive.
        Queue::fake();
        $meeting = ($this->generate)();
        ($this->withSummary)(['status' => MeetingSummary::STATUS_NONE]);
        $meeting->forceFill(['summary' => ['status' => MeetingSummary::STATUS_NONE]])->save();

        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$meeting->id]])
            ->assertOk();

        expect($response->json('data.queued'))->toBe(0)
            ->and($response->json('data.batch_id'))->toBeNull();
    });

    it('keeps a batch id out of the run identity, so a rerun is not a new lock', function () {
        // The batch is bookkeeping for the notification, not part of what was
        // asked for. Folding it into `uniqueId()` would give two presses of the
        // button two different identities, the uniqueness lock would let both
        // through, and the same summary would be paid for twice.
        //
        // Asserted by the *second* press being dropped rather than queued: the
        // fake honours ShouldBeUnique, so a second push would mean the two
        // dispatches looked like different requests. That is the stronger
        // assertion - it is the behaviour, not a string comparison.
        Queue::fake();
        $meeting = ($this->generate)();

        $user = createUserWithPermissions(['board-meeting.sync']);

        $first = actingAs($user)->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$meeting->id]])->assertOk();
        $second = actingAs($user)->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$meeting->id]])->assertOk();

        // Each dispatch still reports its own batch and its own queued count -
        // the lock suppresses the job, not the eligibility answer, so the client
        // is not told the work was skipped when it was merely deduplicated.
        expect($first->json('data.batch_id'))->not->toBe($second->json('data.batch_id'))
            ->and($first->json('data.queued'))->toBe(1)
            ->and($second->json('data.queued'))->toBe(1);

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class, 1);
    });

    it('skips a meeting that gained a summary after the list was rendered', function () {
        Queue::fake();
        $stale = ($this->generate)();
        $fresh = ($this->generate)();
        ($this->withSummary)(['status' => MeetingSummary::STATUS_NONE]);

        // The dialog was rendered a moment ago and somebody decided in between.
        // The rule belongs to the write, not to the list, so the write is where
        // it is enforced — and the answer says what it skipped rather than
        // making the person refresh to find out.
        $stale->forceFill(['summary' => ['status' => MeetingSummary::STATUS_NONE]])->save();

        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$stale->id, $fresh->id]])
            ->assertOk();

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class, 1);
        expect($response->json('data.queued'))->toBe(1)
            ->and($response->json('data.skipped'))->toBe(1)
            ->and($response->json('data.meeting_ids'))->toBe([$fresh->id]);
    });

    it('proposes by default, like every other run', function () {
        Queue::fake();
        $meeting = ($this->generate)();

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$meeting->id]])
            ->assertOk();

        // Tempting to default a bulk run to apply — the meetings have no
        // summary to lose — but a meeting with no summary can still carry tags
        // somebody added by hand, and an applied run re-derives those.
        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->mode === SummaryWriteMode::Review,
        );
    });

    it('passes the chosen pair and mode through', function () {
        Queue::fake();
        $model = seedSummaryModel('local', 'llama3.2');
        $meeting = ($this->generate)();

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'meeting_ids' => [$meeting->id],
                'mode' => 'apply',
                'provider' => 'local',
                'model' => $model->name,
                'fields' => ['topics'],
            ])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->mode === SummaryWriteMode::Apply
                && $job->provider === 'local'
                && $job->model === 'llama3.2'
                // Empty means every field, the same rule the single endpoint and
                // the agent use, so a bulk run needs no special value.
                && $job->fields === [],
        );
    });

    it('accepts an explicit null there too', function () {
        Queue::fake();
        $meeting = ($this->generate)();

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'meeting_ids' => [$meeting->id],
                'provider' => null,
                'model' => null,
            ])
            ->assertOk();

        // "No override" is a value the client sends, not a key it omits: the
        // picker's default is the agent's own binding, expressed as an explicit
        // null. `sometimes` alone would leave a present null to satisfy `string`.
        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->provider === null && $job->model === null,
        );
    });

    it('refuses an empty or absent meeting list', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('meeting_ids');

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('meeting_ids');

        // A button that queues nothing but reports success is worse than one
        // that does not work.
        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
    });

    it('refuses a list longer than a choice', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'meeting_ids' => range(1, 501),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('meeting_ids');

        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
    });

    it('rejects a repeated id rather than paying for it twice', function () {
        Queue::fake();

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [1, 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('meeting_ids.1');
    });
});

describe('an abandoned run', function () {
    it('clears a stamp the queue abandoned, and the row reads as failed', function () {
        $stuck = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_NONE,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);

        $stamp = $stuck->summary;
        $stamp['pending']['started_at'] = now()->subDay()->toIso8601String();
        $stuck->forceFill(['summary' => $stamp])->save();

        $ids = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates')
            ->json('data.*.id');

        $summary = $stuck->fresh()->summary;

        // The stamp stood for a run that never reported, so this is not a
        // meeting somebody else is working on — it is a meeting whose run was
        // lost, and the first-run list is exactly where it belongs.
        expect($ids)->toContain($stuck->id)
            ->and($summary['status'])->toBe(MeetingSummary::STATUS_FAILED)
            ->and($summary['error'])->toBe(MeetingSummary::REASON_ABANDONED)
            ->and($summary['pending'])->toBeNull();
    });

    it('keeps the summary the abandoned run was standing over', function () {
        // Under `Review` the run leaves the row's own content, and so its
        // status, untouched — the stamp is the only thing it adds. Clearing it
        // has to give the row back rather than turning a good summary into a
        // failure: the run proposed nothing, and the content still stands.
        $replaced = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_OK,
            'executive_summary' => 'خلاصهٔ پذیرفته‌شده',
            'fields' => [
                'executive_summary' => [
                    'status' => MeetingSummary::FIELD_OK,
                    'error' => null,
                    'generated_at' => now()->subDay()->toIso8601String(),
                ],
            ],
            'pending' => MeetingSummary::pending()->toArray(),
        ]);

        $stamp = $replaced->summary;
        $stamp['pending']['started_at'] = now()->subDay()->toIso8601String();
        $replaced->forceFill(['summary' => $stamp])->save();

        $user = createUserWithPermissions(['board-meeting.sync']);

        $ungenerated = actingAs($user)
            ->getJson('/api/board-meetings/summary/candidates')
            ->json('data.*.id');
        $regeneratable = actingAs($user)
            ->getJson('/api/board-meetings/summary/candidates?regenerate=1')
            ->json('data.*.id');

        $summary = $replaced->fresh()->summary;

        // Not a first-run candidate (it has content), a replacement candidate
        // (that content is what regenerate is for), and its content survived.
        expect($ungenerated)->not->toContain($replaced->id)
            ->and($regeneratable)->toContain($replaced->id)
            ->and($summary['status'])->toBe(MeetingSummary::STATUS_OK)
            ->and($summary['executive_summary'])->toBe('خلاصهٔ پذیرفته‌شده')
            ->and($summary['pending'])->toBeNull();
    });

    it('leaves a run that is still young alone', function () {
        $young = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_NONE,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);

        $ids = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates')
            ->json('data.*.id');

        // A live run cannot be told from a dead one until its age passes the
        // bounds of a live run, so the rule errs toward leaving it be: clearing
        // a stamp a worker is still behind would pay for the same call twice.
        expect($ids)->not->toContain($young->id)
            ->and($young->fresh()->summary['pending'])->not->toBeNull();
    });

    it('clears a stamp that never recorded when it started', function () {
        // `started_at` is younger than the feature. A row stamped before it
        // existed has no time to compare against, so the row's own `updated_at`
        // stands in — it is when the stamp landed.
        $legacy = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_NONE,
            'pending' => ['status' => MeetingSummary::STATUS_PENDING, 'started_at' => null],
        ]);

        $legacy->timestamps = false;
        $legacy->forceFill(['updated_at' => now()->subDay()])->save();
        $legacy->timestamps = true;

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates')
            ->assertOk();

        expect($legacy->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_FAILED)
            ->and($legacy->fresh()->summary['error'])->toBe(MeetingSummary::REASON_ABANDONED);
    });

    it('reaps before it dispatches, so a lost run is not mistaken for a busy one', function () {
        Queue::fake();

        $stuck = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_FAILED,
            'error' => MeetingSummary::REASON_UNAVAILABLE,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);

        $stamp = $stuck->summary;
        $stamp['pending']['started_at'] = now()->subDay()->toIso8601String();
        $stuck->forceFill(['summary' => $stamp])->save();

        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', ['meeting_ids' => [$stuck->id]])
            ->assertOk();

        // The picker offered it, so the write has to agree — otherwise the
        // button a person just pressed would report nothing queued for the row
        // they were looking at.
        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class, 1);
        expect($response->json('data.queued'))->toBe(1);
    });
});

describe('the regenerate scope', function () {
    it('lists the meetings that already have a summary', function () {
        $ok = ($this->withSummary)(['status' => MeetingSummary::STATUS_OK]);
        $partial = ($this->withSummary)(['status' => MeetingSummary::STATUS_PARTIAL]);
        ($this->withSummary)(['status' => MeetingSummary::STATUS_FAILED]);
        ($this->generate)();
        $inFlight = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_PARTIAL,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);

        $ids = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->getJson('/api/board-meetings/summary/candidates?regenerate=1')
            ->json('data.*.id');

        // The other half of the picker: meetings with something to replace. A
        // failed or absent summary belongs to the first-run list, and a live run
        // belongs to neither — the stamp protects it in both scopes.
        expect($ids)->toEqualCanonicalizing([$ok->id, $partial->id])
            ->and($ids)->not->toContain($inFlight->id);
    });

    it('queues a run for a meeting that already has a summary', function () {
        Queue::fake();
        $meeting = ($this->withSummary)(['status' => MeetingSummary::STATUS_OK]);

        // Only the replacement group: the picker answers for a scope, and an
        // empty first-run group is not an empty request.
        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'regenerate_ids' => [$meeting->id],
            ])
            ->assertOk();

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class, 1);
        expect($response->json('data.queued'))->toBe(1)
            ->and($response->json('data.skipped'))->toBe(0);
    });

    it('skips a meeting with nothing to replace in the regenerate scope', function () {
        Queue::fake();
        $blank = ($this->generate)();

        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'regenerate_ids' => [$blank->id],
            ])
            ->assertOk();

        // The two scopes are disjoint, so an id chosen from the wrong list is
        // skipped rather than quietly run as the other kind of run: the answer
        // says which happened instead of making the person guess.
        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
        expect($response->json('data.queued'))->toBe(0)
            ->and($response->json('data.skipped'))->toBe(1);
    });

    it('still refuses to regenerate over a run in flight', function () {
        Queue::fake();
        $busy = ($this->withSummary)([
            'status' => MeetingSummary::STATUS_PARTIAL,
            'pending' => MeetingSummary::pending()->toArray(),
        ]);

        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'regenerate_ids' => [$busy->id],
            ])
            ->assertOk();

        // Regenerating asks to replace a summary, not to bypass the one guard
        // that keeps two runs of one meeting from overwriting each other.
        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
        expect($response->json('data.queued'))->toBe(0);
    });

    it('runs a mixed selection as one batch, each id under its own scope', function () {
        Queue::fake();
        $blank = ($this->generate)();
        $found = ($this->withSummary)(['status' => MeetingSummary::STATUS_OK]);

        $response = actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'meeting_ids' => [$blank->id],
                'regenerate_ids' => [$found->id],
            ])
            ->assertOk();

        // One dialog, one press, one batch: a person who ticked a meeting that
        // has no summary and one that has a summary asked for two runs, not for
        // two requests, and the progress announcement has to be one story.
        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class, 2);
        expect($response->json('data.queued'))->toBe(2)
            ->and($response->json('data.skipped'))->toBe(0)
            ->and($response->json('data.meeting_ids'))->toEqualCanonicalizing([$blank->id, $found->id]);
    });

    it('refuses an id named in both groups', function () {
        Queue::fake();
        $meeting = ($this->withSummary)(['status' => MeetingSummary::STATUS_OK]);

        // One id is one intent. A client that lists it twice is not asking for
        // two runs, it is asking a question with no answer, and the risk of
        // guessing is paying for the same summary twice.
        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'meeting_ids' => [$meeting->id],
                'regenerate_ids' => [$meeting->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('regenerate_ids');

        Queue::assertNotPushed(GenerateBoardMeetingSummaryJob::class);
    });
});

describe('choosing a model for one meeting', function () {
    it('carries the named pair onto the job', function () {
        Queue::fake();
        seedSummaryModel('local', 'llama3.2');
        $meeting = ($this->generate)();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", [
                'provider' => 'local',
                'model' => 'llama3.2',
            ])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->provider === 'local'
                && $job->model === 'llama3.2',
        );
    });

    it('rejects a model name that no provider serves', function () {
        $meeting = ($this->generate)();

        // The refusal is field-keyed: a form has to know which select to mark,
        // and a message-only 422 renders as a banner while both look fine.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", [
                'provider' => 'local',
                'model' => 'no-such-model',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('model');
    });

    it('rejects a provider that does not exist, against the provider field', function () {
        $meeting = ($this->generate)();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", [
                'provider' => 'ghost',
                'model' => 'llama3.2',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');
    });

    it('rejects a model the named provider does not serve', function () {
        seedSummaryProvider('gateway', makeDefault: false);
        seedSummaryModel('gateway', 'gpt-4o-mini');
        $meeting = ($this->generate)();

        // A model name is unique only within a provider, so the pair is what
        // makes a request answerable: `local` does not serve `gpt-4o-mini`.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", [
                'provider' => 'local',
                'model' => 'gpt-4o-mini',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('model');
    });

    it('accepts an explicit null as the agent bound model', function () {
        Queue::fake();
        $meeting = ($this->generate)();

        // `sometimes` skips an *absent* key, but the picker's default is the
        // key present-and-null. Without `nullable` that is the one value the
        // boundary refuses, and the button fails before queueing anything.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", [
                'provider' => null,
                'model' => null,
            ])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->provider === null && $job->model === null,
        );
    });

    it('accepts a model name with no provider at all', function () {
        Queue::fake();
        seedSummaryModel('local', 'llama3.2');
        $meeting = ($this->generate)();

        // The ordinary case: "this model, on whichever provider the run would
        // have used anyway". Refusing it would make the simpler request the
        // broken one.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", ['model' => 'llama3.2'])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->provider === null
                && $job->model === 'llama3.2',
        );
    });

    it('keeps two runs on different models from being the same request', function () {
        // Asking twice on purpose with two models is a comparison, so the
        // uniqueness lock must not swallow the second.
        expect((new GenerateBoardMeetingSummaryJob(1, [], 'local', 'llama3.2'))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(1, [], 'local', 'gpt-4o'))->uniqueId());
    });

    it('keeps two runs on the same model behind different providers apart', function () {
        // The pair is the identity: one model name under two gateways is two
        // different requests, and collapsing them would drop a paid run.
        expect((new GenerateBoardMeetingSummaryJob(1, [], 'local', 'gpt-4o'))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(1, [], 'gateway', 'gpt-4o'))->uniqueId());
    });
});
