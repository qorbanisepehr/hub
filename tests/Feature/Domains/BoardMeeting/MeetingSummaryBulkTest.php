<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;
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
    $this->provider = AiProvider::factory()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);

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
        $second = AiProvider::factory()->create(['name' => 'gateway']);
        AiModel::factory()->create(['provider_id' => $this->provider->id, 'model_name' => 'llama3.2']);
        AiModel::factory()->create(['provider_id' => $second->id, 'model_name' => 'gpt-4o-mini']);

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
        AiModel::factory()->create(['provider_id' => $this->provider->id, 'model_name' => 'live']);
        AiModel::factory()->inactive()->create(['provider_id' => $this->provider->id, 'model_name' => 'retired']);
        $dead = AiProvider::factory()->inactive()->create(['name' => 'dead']);
        AiModel::factory()->create(['provider_id' => $dead->id, 'model_name' => 'unreachable']);

        $names = actingAs(createUserWithPermissions(['ai.provider.view']))
            ->getJson('/api/ai/models')
            ->json('data.*.model_name');

        // The list has to match what the resolver will accept. Offering a row
        // the resolver then refuses is a control that lies about what it does.
        expect($names)->toBe(['live']);
    });

    it('never carries the provider row it does not need', function () {
        AiModel::factory()->create(['provider_id' => $this->provider->id]);

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

    it('passes the chosen model and mode through', function () {
        Queue::fake();
        $model = AiModel::factory()->create(['provider_id' => $this->provider->id]);
        $meeting = ($this->generate)();

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/summary/bulk', [
                'meeting_ids' => [$meeting->id],
                'mode' => 'apply',
                'model_id' => $model->id,
                'fields' => ['topics'],
            ])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->mode === SummaryWriteMode::Apply
                && $job->modelId === $model->id
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
                'model_id' => null,
            ])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->modelId === null,
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

describe('choosing a model for one meeting', function () {
    it('carries the model id onto the job', function () {
        Queue::fake();
        $model = AiModel::factory()->create(['provider_id' => $this->provider->id]);
        $meeting = ($this->generate)();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", ['model_id' => $model->id])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->modelId === $model->id,
        );
    });

    it('rejects a model id that is not a number', function () {
        $meeting = ($this->generate)();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", ['model_id' => 'gpt-4o'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('model_id');
    });

    it('accepts an explicit null as the agent bound model', function () {
        Queue::fake();
        $meeting = ($this->generate)();

        // `sometimes` skips an *absent* key, but the picker's default is the
        // key present-and-null. Without `nullable` that is the one value the
        // boundary refuses, and the button fails before queueing anything.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", ['model_id' => null])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->modelId === null,
        );
    });

    it('accepts an id that no longer exists rather than failing the run', function () {
        Queue::fake();
        $meeting = ($this->generate)();

        // Validated as a plain integer, not against the Ai domain's table: that
        // table is the Ai domain's, and a queued job whose model was deleted in
        // Settings has to still run when it finally gets its turn.
        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", ['model_id' => 999_999])
            ->assertOk();

        Queue::assertPushed(GenerateBoardMeetingSummaryJob::class);
    });

    it('keeps two runs on different models from being the same request', function () {
        $a = AiModel::factory()->create(['provider_id' => $this->provider->id]);
        $b = AiModel::factory()->create(['provider_id' => $this->provider->id]);

        // Asking twice on purpose with two models is a comparison, so the
        // uniqueness lock must not swallow the second.
        expect((new GenerateBoardMeetingSummaryJob(1, [], $a->id))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(1, [], $b->id))->uniqueId());
    });
});
