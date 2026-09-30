<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Models\AiAgent;
use App\Domains\Ai\Models\AiProvider;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Services\AiProviderResolver;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSummarizer;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\Fixtures\Ai\FieldedEchoAgent;
use Tests\Fixtures\Ai\StructuredEchoAgent;

/*
 * D20's five fields, D21's single structured call, D23's queued run, and the
 * per-field status map D26 needs. The shape of the stored artifact is a
 * contract (ADR-003), so most of what follows asserts that a value which
 * should never reach the row does not.
 */

beforeEach(function () {
    AiProvider::factory()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);

    $this->summarizer = app(BoardMeetingSummarizer::class);
    $this->meeting = BoardMeeting::factory()->create([
        'title' => 'جلسهٔ شورا',
        'minutes' => 'مصوبهٔ شمارهٔ ۴۱ تصویب شد.',
    ]);
});

/**
 * Run the summarizer once and hand back the prompt the agent actually saw.
 *
 * An arrow function cannot be used to capture this: it captures by value, so
 * assigning to `$sent` inside it would write a local and leave the assertion
 * looking at nothing.
 */
function capturePrompt(Closure $target): string
{
    $sent = null;

    MeetingSummaryAgent::fake(function (string $prompt) use (&$sent): array {
        $sent = $prompt;

        return ['executive_summary' => 'ok'];
    });

    app(BoardMeetingSummarizer::class)->summarize($target());

    return (string) $sent;
}

describe('the agent schema', function () {
    it('declares every field when no subset is given', function () {
        $schema = (new MeetingSummaryAgent)->schema(new JsonSchemaTypeFactory);

        expect(array_keys($schema))->toBe([
            'executive_summary',
            'topics',
            'bullet_points',
            'key_decisions',
            'suggested_tags',
        ]);
    });

    it('drops the unrequested fields from the schema, not just from the prompt', function () {
        $schema = (new MeetingSummaryAgent)
            ->withFields(['key_decisions', 'suggested_tags'])
            ->schema(new JsonSchemaTypeFactory);

        expect(array_keys($schema))->toBe(['key_decisions', 'suggested_tags']);
    });

    it('treats an empty subset as every field', function () {
        expect(MeetingSummaryField::fromValues([]))->toBe(MeetingSummaryField::cases());
    });

    it('does not let one run narrow another run', function () {
        $agent = new MeetingSummaryAgent;

        $agent->withFields(['topics']);

        // "Restrict this run" must be last-wins on a fresh instance; an
        // in-place write would leave the SDK prompting a narrowed agent for a
        // run that asked for everything.
        expect(array_keys($agent->schema(new JsonSchemaTypeFactory)))->toHaveCount(5);
    });

    it('rejects a field outside the vocabulary', function () {
        expect(fn () => MeetingSummaryField::fromValues(['vibes']))
            ->toThrow(ValueError::class);
    });

    it('types every list field as a list of strings', function () {
        $schema = (new MeetingSummaryAgent)->schema(new JsonSchemaTypeFactory);

        expect($schema['executive_summary']->toArray()['type'])->toBe('string')
            ->and($schema['topics']->toArray()['type'])->toBe('array')
            ->and($schema['topics']->toArray()['items']['type'])->toBe('string');
    });
});

describe('the runner hand-off', function () {
    it('passes the requested subset to a fielded agent', function () {
        $probe = new FieldedEchoAgent;
        app()->instance(FieldedEchoAgent::class, $probe);
        FieldedEchoAgent::fake([['asked_for' => ['topics']]]);

        app(AiAgentRunner::class)->run(
            key: 'test.fielded',
            prompt: 'go',
            agentClass: FieldedEchoAgent::class,
            settings: ['fields' => ['topics', 'key_decisions']],
        );

        expect($probe->askedFor)->toBe(['topics', 'key_decisions']);
    });

    it('leaves an agent that has no fields alone', function () {
        // The simplest agents must keep needing nothing but `Agent`, so a
        // settings array carrying a subset must not be an error for them.
        StructuredEchoAgent::fake([['executive_summary' => 'a summary', 'topics' => ['a']]]);

        $run = app(AiAgentRunner::class)->run(
            key: 'test.plain',
            prompt: 'go',
            agentClass: StructuredEchoAgent::class,
            settings: ['fields' => ['topics']],
        );

        expect($run->structured)->toHaveKeys(['executive_summary', 'topics']);
    });
});

describe('the prompt', function () {
    it('carries the minutes, the agenda and the attendees', function () {
        $meeting = BoardMeeting::factory()
            ->withAgenda(new AgendaItem(1, 'بودجه'))
            ->withAttendees(new Attendee('خانم الف'), new Attendee('آقای ب', isPresent: false))
            ->create(['minutes' => 'متن صورتجلسه']);

        $sent = capturePrompt(fn () => $meeting);

        expect($sent)->toContain('متن صورتجلسه')
            ->and($sent)->toContain('بودجه')
            ->and($sent)->toContain('خانم الف')
            ->and($sent)->toContain('آقای ب');
    });

    it('marks an absent member rather than dropping them', function () {
        // Who was in the room changes what a decision meant, so absence is
        // part of the record rather than a silently shorter list.
        expect(capturePrompt(fn () => BoardMeeting::factory()
            ->withAttendees(new Attendee('آقای ب', isPresent: false))
            ->create()))->toContain('absent');
    });

    it('leaves out the registry bookkeeping', function () {
        $sent = capturePrompt(fn () => $this->meeting);

        // `origin` and `provenance` describe where the row came from, not what
        // the board did; a model that reads them summarizes the import.
        expect($sent)->not->toContain('farzin')
            ->and($sent)->not->toContain('provenance');
    });

    it('omits sections the meeting does not have', function () {
        $bare = BoardMeeting::factory()->create(['title' => null, 'minutes' => null, 'agenda' => [], 'attendees' => []]);

        $sent = capturePrompt(fn () => $bare);

        expect($sent)->not->toContain('Agenda:')
            ->and($sent)->not->toContain('Attendees:')
            ->and($sent)->not->toContain('Minutes:');
    });
});

describe('reading a run', function () {
    it('is ok when every requested field came back', function () {
        MeetingSummaryAgent::fake([[
            'executive_summary' => 'خلاصه',
            'topics' => ['بودجه'],
            'bullet_points' => ['الف'],
            'key_decisions' => ['مصوبهٔ ۴۱'],
            'suggested_tags' => ['budget'],
        ]]);

        $summary = $this->summarizer->summarize($this->meeting);

        expect($summary->status)->toBe(MeetingSummary::STATUS_OK)
            ->and($summary->values['executive_summary'])->toBe('خلاصه')
            ->and($summary->values['key_decisions'])->toBe(['مصوبهٔ ۴۱'])
            ->and($summary->error)->toBeNull();
    });

    it('is partial when the model skipped one of the fields it was asked for', function () {
        MeetingSummaryAgent::fake([[
            'executive_summary' => 'خلاصه',
            'topics' => ['بودجه'],
            'bullet_points' => ['الف'],
            'key_decisions' => [],
            'suggested_tags' => ['budget'],
        ]]);

        $summary = $this->summarizer->summarize($this->meeting);

        expect($summary->status)->toBe(MeetingSummary::STATUS_PARTIAL)
            ->and($summary->fields['key_decisions']['status'])->toBe(MeetingSummary::FIELD_FAILED)
            ->and($summary->fields['key_decisions']['error'])->toBe(MeetingSummary::REASON_EMPTY)
            ->and($summary->fields['topics']['status'])->toBe(MeetingSummary::FIELD_OK);
    });

    it('reports an unrequested field as absent rather than empty', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        $summary = $this->summarizer->summarize($this->meeting, ['executive_summary']);

        // "asked and came back empty" and "never asked" must not look alike,
        // or a later regeneration has nothing to tell it apart from a success.
        expect($summary->values)->toHaveKey('executive_summary')
            ->and($summary->values)->not->toHaveKey('topics');
    });

    it('fails when the model answered with prose instead of structure', function () {
        MeetingSummaryAgent::fake(['این یک خلاصه است.']);

        $summary = $this->summarizer->summarize($this->meeting, ['executive_summary']);

        expect($summary->status)->toBe(MeetingSummary::STATUS_FAILED)
            ->and($summary->error)->toBe(MeetingSummary::REASON_NO_STRUCTURED_OUTPUT);
    });

    it('fails when the structure holds nothing usable', function () {
        MeetingSummaryAgent::fake([['executive_summary' => '   ', 'topics' => []]]);

        $summary = $this->summarizer->summarize($this->meeting);

        expect($summary->status)->toBe(MeetingSummary::STATUS_FAILED)
            ->and($summary->error)->toBe(MeetingSummary::REASON_NO_FIELDS_RETURNED);
    });

    it('refuses a list value where a single string belongs', function () {
        MeetingSummaryAgent::fake([['executive_summary' => ['a', 'b']]]);

        $summary = $this->summarizer->summarize($this->meeting, ['executive_summary']);

        expect($summary->status)->toBe(MeetingSummary::STATUS_FAILED);
    });

    it('drops blank and duplicated list entries', function () {
        MeetingSummaryAgent::fake([['key_decisions' => ['الف', '', 'الف', '  ', 'ب']]]);

        $summary = $this->summarizer->summarize($this->meeting, ['key_decisions']);

        // A model that repeats a decision, or pads a list with blanks to hit
        // a count, must not turn one decision into two rows of evidence.
        expect($summary->values['key_decisions'])->toBe(['الف', 'ب']);
    });

    it('records which model wrote it', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        $summary = $this->summarizer->summarize($this->meeting, ['executive_summary']);

        expect($summary->provider)->toBe('local')
            ->and($summary->usage)->toHaveKeys(['input_tokens', 'output_tokens', 'total_tokens']);
    });

    it('names the language outright instead of inferring it from the minutes', function () {
        // The archive minutes are OCR text carrying stray glyphs from other
        // scripts, and a model told to "answer in the language of the minutes"
        // reads that as licence to answer in English. A Persian board record
        // summarized in English is a wrong answer, not a worse one.
        expect((new MeetingSummaryAgent)->instructions())
            ->toContain('Persian')
            ->and((new MeetingSummaryAgent)->instructions())->not->toContain('language of the minutes');
    });

    it('follows the hub locale rather than a hardcoded one', function () {
        $original = app()->getLocale();

        try {
            app()->setLocale('en');

            expect((new MeetingSummaryAgent)->instructions())->toContain('English');
        } finally {
            app()->setLocale($original);
        }
    });

    it('names the agent key in the stored shape', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        $stored = $this->summarizer->summarize($this->meeting, ['executive_summary'])->toArray();

        expect($stored['agent'])->toBe('board-meeting.summary')
            ->and($stored['status'])->toBe(MeetingSummary::STATUS_OK);
    });
});

describe('an unregistered agent', function () {
    it('still runs, so a domain can ship before an administrator configures it', function () {
        // D24 makes an empty AI registry a supported state, which means the
        // summary agent has to work on a hub where nobody has ever opened the
        // AI settings tab. The class is the fallback; the registry row is what
        // production uses, and the day it exists it takes over.
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        expect(AiAgent::query()->where('key', MeetingSummaryAgent::REGISTRY_KEY)->exists())->toBeFalse();

        $summary = $this->summarizer->summarize($this->meeting, ['executive_summary']);

        expect($summary->status)->toBe(MeetingSummary::STATUS_OK)
            ->and($summary->toArray()['agent'])->toBe(MeetingSummaryAgent::REGISTRY_KEY);
    });

    it('prefers the registry row once one exists', function () {
        // Otherwise "create a row in Settings" would be a change that silently
        // does nothing until a deploy, which is the opposite of D28's promise.
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        AiAgent::factory()->create([
            'key' => MeetingSummaryAgent::REGISTRY_KEY,
            'agent_class' => MeetingSummaryAgent::class,
        ]);

        expect($this->summarizer->summarize($this->meeting, ['executive_summary'])->status)
            ->toBe(MeetingSummary::STATUS_OK);

        expect(AiAgent::query()->where('key', MeetingSummaryAgent::REGISTRY_KEY)->count())->toBe(1);
    });
});

describe('the job', function () {
    it('stores the artifact on the meeting', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]]);

        (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['executive_summary', 'topics']))
            ->handle($this->summarizer);

        $stored = $this->meeting->fresh()->summary;

        expect($stored['executive_summary'])->toBe('خلاصه')
            ->and($stored['topics'])->toBe(['بودجه'])
            ->and($stored['status'])->toBe(MeetingSummary::STATUS_OK);
    });

    it('marks the meeting pending while the model is still running', function () {
        MeetingSummaryAgent::fake(function (): array {
            // Read inside the run: a bulk action leaves most of the registry
            // in flight at once, and "queued" must not look like "never asked".
            expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_PENDING);

            return ['executive_summary' => 'خلاصه'];
        });

        (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['executive_summary']))
            ->handle($this->summarizer);

        expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_OK);
    });

    it('leaves a human-owned meeting unlocked', function () {
        $locked = BoardMeeting::factory()->humanOwned()->create(['source_locked_at' => $stamp = now()]);
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        (new GenerateBoardMeetingSummaryJob($locked->id, ['executive_summary']))->handle($this->summarizer);

        // A summary is regenerated on a locked meeting on purpose — that is
        // the whole point of being able to regenerate it — so generating must
        // never freeze the source's view of the row.
        expect($locked->fresh()->source_locked_at?->toDateTimeString())->toBe($stamp->toDateTimeString());
    });

    it('does nothing when the meeting is gone', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        (new GenerateBoardMeetingSummaryJob(999999, ['executive_summary']))->handle($this->summarizer);

        MeetingSummaryAgent::assertNeverPrompted();
    });

    it('records a permanent failure instead of retrying forever', function () {
        // No provider at all: the run cannot start, and no number of retries
        // will conjure one.
        $this->meeting->forceFill(['summary' => null])->save();
        AiProvider::query()->delete();
        app(AiProviderResolver::class)->forget();

        (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['executive_summary']))->handle($this->summarizer);

        expect($this->meeting->fresh()->summary)
            ->toMatchArray(['status' => MeetingSummary::STATUS_FAILED, 'error' => MeetingSummary::REASON_NOT_CONFIGURED]);
    });

    it('lets a transient failure reach the queue', function () {
        // A dead URL or a rejected key is worth retrying, so it must not be
        // swallowed into a written `failed`.
        MeetingSummaryAgent::fake(fn () => throw new RuntimeException('connection reset'));

        expect(fn () => (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['executive_summary']))
            ->handle($this->summarizer))
            ->toThrow(RuntimeException::class);

        expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_PENDING);
    });
});

describe('regenerating part of a summary', function () {
    beforeEach(function () {
        $this->meeting->forceFill(['summary' => [
            'status' => MeetingSummary::STATUS_OK,
            'executive_summary' => 'خلاصهٔ قبلی',
            'topics' => ['بودجه'],
            'bullet_points' => ['الف'],
            'key_decisions' => ['مصوبهٔ ۴۱'],
            'suggested_tags' => ['budget'],
            'fields' => [
                'executive_summary' => ['status' => 'ok', 'generated_at' => '2026-09-01T10:00:00+00:00'],
                'topics' => ['status' => 'ok', 'generated_at' => '2026-09-01T10:00:00+00:00'],
                'bullet_points' => ['status' => 'ok', 'generated_at' => '2026-09-01T10:00:00+00:00'],
                'key_decisions' => ['status' => 'ok', 'generated_at' => '2026-09-01T10:00:00+00:00'],
                'suggested_tags' => ['status' => 'ok', 'generated_at' => '2026-09-01T10:00:00+00:00'],
            ],
            'agent' => 'board-meeting.summary',
            'provider' => 'old',
            'model' => 'old-model',
            'usage' => null,
            'error' => null,
            'generated_at' => '2026-09-01T10:00:00+00:00',
        ]])->save();
    });

    it('replaces only the field it was asked for', function () {
        MeetingSummaryAgent::fake([['key_decisions' => ['مصوبهٔ ۴۲']]]);

        (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['key_decisions']))->handle($this->summarizer);

        $stored = $this->meeting->fresh()->summary;

        expect($stored['key_decisions'])->toBe(['مصوبهٔ ۴۲'])
            ->and($stored['executive_summary'])->toBe('خلاصهٔ قبلی')
            ->and($stored['topics'])->toBe(['بودجه'])
            ->and($stored['suggested_tags'])->toBe(['budget']);
    });

    it('keeps the old answer when the regeneration comes back empty', function () {
        MeetingSummaryAgent::fake([['key_decisions' => []]]);

        (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['key_decisions']))->handle($this->summarizer);

        $stored = $this->meeting->fresh()->summary;

        // Discarding a good answer because a later run was worse helps nobody,
        // but the state map must still say the field failed — and its missing
        // timestamp is what tells the UI the value on screen predates the run.
        expect($stored['key_decisions'])->toBe(['مصوبهٔ ۴۱'])
            ->and($stored['fields']['key_decisions']['status'])->toBe(MeetingSummary::FIELD_FAILED)
            ->and($stored['fields']['key_decisions']['generated_at'])->toBeNull()
            ->and($stored['status'])->toBe(MeetingSummary::STATUS_PARTIAL);
    });

    it('does not blank the row while the second run is in flight', function () {
        MeetingSummaryAgent::fake(function (): array {
            expect($this->meeting->fresh()->summary['executive_summary'])->toBe('خلاصهٔ قبلی');

            return ['key_decisions' => ['مصوبهٔ ۴۲']];
        });

        (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['key_decisions']))->handle($this->summarizer);

        expect($this->meeting->fresh()->summary['executive_summary'])->toBe('خلاصهٔ قبلی');
    });

    it('names the model that wrote the most recent thing written', function () {
        MeetingSummaryAgent::fake([['key_decisions' => ['مصوبهٔ ۴۲']]]);

        (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['key_decisions']))->handle($this->summarizer);

        $stored = $this->meeting->fresh()->summary;

        expect($stored['provider'])->toBe('local')
            ->and($stored['model'])->not->toBe('old-model');
    });
});

describe('reading a stored artifact', function () {
    it('treats a row that was never generated as no artifact at all', function () {
        expect(MeetingSummary::fromArray(null))->toBeNull()
            ->and(MeetingSummary::fromArray([]))->toBeNull();
    });

    it('reads back what it wrote', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]]);

        $summary = $this->summarizer->summarize($this->meeting, ['executive_summary', 'topics']);
        $round = MeetingSummary::fromArray($summary->toArray());

        expect($round->status)->toBe($summary->status)
            ->and($round->values)->toBe($summary->values)
            ->and($round->fields)->toBe($summary->fields);
    });

    it('refuses to trust a shape it no longer recognises', function () {
        // The rows most likely to need regenerating are the oldest ones, so a
        // stale artifact must not be the one thing the feature cannot fix.
        $round = MeetingSummary::fromArray([
            'status' => 'exploded',
            'executive_summary' => 'خلاصه',
            'who_knows' => ['a'],
        ]);

        expect($round->status)->toBe(MeetingSummary::STATUS_FAILED)
            ->and($round->values)->toHaveKey('executive_summary')
            ->and($round->values)->not->toHaveKey('who_knows');
    });
});

describe('job identity', function () {
    it('treats the same subset in a different order as the same request', function () {
        expect((new GenerateBoardMeetingSummaryJob(7, ['topics', 'key_decisions']))->uniqueId())
            ->toBe((new GenerateBoardMeetingSummaryJob(7, ['key_decisions', 'topics']))->uniqueId());
    });

    it('keeps a different subset a different request', function () {
        // D26's promise: regenerating one field must not be dropped by the
        // lock that exists to stop a duplicate of the same run.
        expect((new GenerateBoardMeetingSummaryJob(7, ['topics']))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(7, ['key_decisions']))->uniqueId());
    });

    it('keeps a different meeting a different request', function () {
        expect((new GenerateBoardMeetingSummaryJob(7))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(8))->uniqueId());
    });

    it('is implemented as a unique job', function () {
        expect(new GenerateBoardMeetingSummaryJob(7))->toBeInstanceOf(ShouldBeUnique::class);
    });
});
