<?php

use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Tagging\Ai\TagConsolidationAgent;
use App\Domains\Tagging\Events\TagMergeAccepted;
use App\Domains\Tagging\Jobs\ProposeTagMergesJob;
use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Services\TagConsolidator;
use App\Domains\Tagging\Services\TagService;
use App\Models\User;
use App\Support\Realtime\RunProgressed;
use App\Support\Realtime\RunState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedSummaryProvider();
});

describe('POST /api/tags/consolidate', function () {
    it('forbids a user without tag.manage', function () {
        actingAs(User::factory()->create())
            ->postJson('/api/tags/consolidate')
            ->assertForbidden();
    });

    it('queues the run and answers 202 with its id', function () {
        Queue::fake();

        $user = createUserWithPermissions(['tag.manage']);

        $response = actingAs($user)->postJson('/api/tags/consolidate')->assertStatus(202);

        expect($response->json('data.run_id'))->toBeString()->not->toBe('');

        Queue::assertPushed(
            ProposeTagMergesJob::class,
            fn (ProposeTagMergesJob $job): bool => $job->requestedBy === $user->getKey()
                && $job->runId === $response->json('data.run_id'),
        );
    });
});

/*
 * The proposal itself, which is still computed on demand and still not stored.
 * The queued run is a nudge; this is where the groups leave the server, so the
 * assertions about *what* the model produced live here.
 */
describe('GET /api/tags/consolidate', function () {
    it('forbids a user without tag.manage', function () {
        actingAs(User::factory()->create())
            ->getJson('/api/tags/consolidate')
            ->assertForbidden();
    });

    it('returns validated groups, dropping unknown slugs and double-claims', function () {
        Tag::create(['name' => 'پروژه سونگون', 'slug' => 'soongon']);
        Tag::create(['name' => 'پروژه سونگون (فاز ۱)', 'slug' => 'soongon-phase-1']);
        Tag::create(['name' => 'سونگون', 'slug' => 'soongon-short']);

        TagConsolidationAgent::fake([['groups' => [
            [
                'canonical' => 'soongon',
                'duplicates' => ['soongon-phase-1', 'soongon-short', 'ghost'],
                'reason' => 'یک پروژه‌اند',
            ],
            [
                // Its canonical was already claimed as a duplicate above, which
                // would make one tag both kept and deleted — dropped.
                'canonical' => 'soongon-short',
                'duplicates' => ['soongon'],
                'reason' => 'تکراری',
            ],
        ]]]);

        $user = createUserWithPermissions(['tag.manage']);

        $response = actingAs($user)->getJson('/api/tags/consolidate')->assertOk();

        expect($response->json('data.groups'))->toHaveCount(1)
            ->and($response->json('data.groups.0.canonical.slug'))->toBe('soongon')
            ->and(array_column($response->json('data.groups.0.duplicates'), 'slug'))
            ->toBe(['soongon-phase-1', 'soongon-short'])
            ->and($response->json('data.groups.0.reason'))->toBe('یک پروژه‌اند');
    });

    it('drops a group whose canonical is not a known tag', function () {
        Tag::create(['name' => 'بودجه', 'slug' => 'budget']);

        TagConsolidationAgent::fake([['groups' => [
            ['canonical' => 'does-not-exist', 'duplicates' => ['budget'], 'reason' => 'x'],
        ]]]);

        $user = createUserWithPermissions(['tag.manage']);

        $response = actingAs($user)->getJson('/api/tags/consolidate')->assertOk();

        expect($response->json('data.groups'))->toBe([]);
    });

    it('reports nothing to merge as an empty list', function () {
        Tag::create(['name' => 'بودجه', 'slug' => 'budget']);

        TagConsolidationAgent::fake([['groups' => []]]);

        $user = createUserWithPermissions(['tag.manage']);

        $response = actingAs($user)->getJson('/api/tags/consolidate')->assertOk();

        expect($response->json('data.groups'))->toBe([]);
    });

    it('sends the whole vocabulary with slugs and usage counts to the model', function () {
        $meeting = BoardMeeting::factory()->create();

        app(TagService::class)->syncTagProposals($meeting, [
            ['name' => 'پروژه سونگون', 'slug' => 'soongon'],
        ]);
        Tag::create(['name' => 'بودجه', 'slug' => 'budget']);

        $sent = null;

        TagConsolidationAgent::fake(function (string $prompt) use (&$sent): array {
            $sent = $prompt;

            return ['groups' => []];
        });

        $user = createUserWithPermissions(['tag.manage']);
        actingAs($user)->getJson('/api/tags/consolidate')->assertOk();

        expect($sent)
            ->toContain('1 | پروژه سونگون | soongon')
            ->toContain('0 | بودجه | budget');
    });
});

describe('ProposeTagMergesJob', function () {
    it('reports how many groups the model proposed', function () {
        Tag::create(['name' => 'پروژه سونگون', 'slug' => 'soongon']);
        Tag::create(['name' => 'سونگون', 'slug' => 'soongon-short']);

        TagConsolidationAgent::fake([['groups' => [
            ['canonical' => 'soongon', 'duplicates' => ['soongon-short'], 'reason' => 'یکی است'],
        ]]]);

        Event::fake([RunProgressed::class]);

        (new ProposeTagMergesJob(User::factory()->create()->getKey(), 'run-1'))
            ->handle(app(TagConsolidator::class));

        Event::assertDispatched(
            RunProgressed::class,
            fn (RunProgressed $event): bool => $event->state === RunState::Finished
                && $event->runId === 'run-1'
                && $event->channel === 'tags'
                && $event->context['groups'] === 1,
        );
    });

    it('reports a missing provider by the stable reason the client already handles', function () {
        // No provider at all: the runner is the thing that decides a missing
        // registry is a state rather than a crash, so it is what is stubbed.
        $this->mock(AiAgentRunner::class)
            ->shouldReceive('run')
            ->once()
            ->andThrow(AiNotConfigured::forAgent('tagging.consolidate'));

        Event::fake([RunProgressed::class]);

        (new ProposeTagMergesJob(User::factory()->create()->getKey(), 'run-2'))
            ->handle(app(TagConsolidator::class));

        Event::assertDispatched(
            RunProgressed::class,
            fn (RunProgressed $event): bool => $event->state === RunState::Failed
                && $event->context['reason'] === 'not_configured',
        );
    });
});

describe('POST /api/tags/consolidate/accept', function () {
    it('forbids a user without tag.manage', function () {
        actingAs(User::factory()->create())
            ->postJson('/api/tags/consolidate/accept', ['groups' => []])
            ->assertForbidden();
    });

    it('moves attachments to the canonical tag and deletes the duplicates', function () {
        Event::fake([TagMergeAccepted::class]);

        $first = BoardMeeting::factory()->create();
        $second = BoardMeeting::factory()->create();
        $service = app(TagService::class);

        $service->syncTagProposals($first, [
            ['name' => 'پروژه سونگون', 'slug' => 'soongon'],
            ['name' => 'پروژه سونگون (فاز ۱)', 'slug' => 'soongon-phase-1'],
        ]);
        $service->syncTagProposals($second, [
            ['name' => 'پروژه سونگون (فاز ۱)', 'slug' => 'soongon-phase-1'],
        ]);

        $user = createUserWithPermissions(['tag.manage']);

        $response = actingAs($user)->postJson('/api/tags/consolidate/accept', [
            'groups' => [[
                'canonical' => 'soongon',
                'duplicates' => ['soongon-phase-1'],
            ]],
        ])->assertOk();

        expect($response->json('merged'))->toBe(1)
            ->and($response->json('attachments_moved'))->toBe(2)
            ->and(Tag::where('slug', 'soongon-phase-1')->exists())->toBeFalse()
            ->and($first->refresh()->tags->pluck('slug')->all())->toBe(['soongon'])
            ->and($second->refresh()->tags->pluck('slug')->all())->toBe(['soongon']);

        Event::assertDispatched(TagMergeAccepted::class);
    });

    it('folds a duplicate onto an entity that already carries the canonical', function () {
        $meeting = BoardMeeting::factory()->create();
        $service = app(TagService::class);

        $service->syncTagProposals($meeting, [
            ['name' => 'بودجه', 'slug' => 'budget'],
            ['name' => 'بودجه سالانه', 'slug' => 'budget-annual'],
        ]);

        $user = createUserWithPermissions(['tag.manage']);

        actingAs($user)->postJson('/api/tags/consolidate/accept', [
            'groups' => [['canonical' => 'budget', 'duplicates' => ['budget-annual']]],
        ])->assertOk();

        expect($meeting->refresh()->tags->pluck('slug')->all())->toBe(['budget']);
    });

    it('ignores slugs that no longer exist and merges nothing', function () {
        Tag::create(['name' => 'بودجه', 'slug' => 'budget']);

        $user = createUserWithPermissions(['tag.manage']);

        $response = actingAs($user)->postJson('/api/tags/consolidate/accept', [
            'groups' => [
                ['canonical' => 'budget', 'duplicates' => ['ghost']],
                ['canonical' => 'missing', 'duplicates' => ['budget']],
            ],
        ])->assertOk();

        expect($response->json('merged'))->toBe(0)
            ->and(Tag::where('slug', 'budget')->exists())->toBeTrue();
    });

    it('rejects a malformed group', function () {
        $user = createUserWithPermissions(['tag.manage']);

        actingAs($user)->postJson('/api/tags/consolidate/accept', [
            'groups' => [['canonical' => 'budget']],
        ])->assertUnprocessable();
    });
});
