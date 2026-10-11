<?php

use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Services\AiProviderResolver;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Ai\Value\AiRegistryKey;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Events\MeetingSummaryGenerated;
use App\Domains\BoardMeeting\Exceptions\NoPendingSummary;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSummarizer;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Services\TagService;
use App\Support\Realtime\RunProgressed;
use App\Support\Realtime\RunState;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\Request;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\ObjectSchema;
use Tests\Fixtures\Ai\FieldedEchoAgent;
use Tests\Fixtures\Ai\StructuredEchoAgent;

/*
 * D20's five fields, D21's single structured call, D23's queued run, and the
 * per-field status map D26 needs. The shape of the stored artifact is a
 * contract (ADR-003), so most of what follows asserts that a value which
 * should never reach the row does not.
 */

beforeEach(function () {
    seedSummaryProvider();

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

/**
 * Run the queued job the way A4's tests mean it: straight into the record.
 *
 * The job's own default is `Review`, because a caller that forgets a mode must
 * not overwrite anything. These tests are about what a run does to the record,
 * so they say `Apply` out loud — and the review half has its own group below,
 * where the mode is the thing under test.
 *
 * @param  list<string>  $fields
 */
function runSummaryJob(
    int $meetingId,
    array $fields = [],
    ?SummaryWriteMode $mode = null,
    ?string $provider = null,
    ?string $model = null,
    ?int $requestedBy = null,
): void {
    (new GenerateBoardMeetingSummaryJob(
        $meetingId,
        $fields,
        $provider,
        $model,
        $mode ?? SummaryWriteMode::Apply,
        requestedBy: $requestedBy,
    ))->handle(app(BoardMeetingSummarizer::class), app(MeetingSummaryApplier::class));
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

    it('makes every field required, so the model cannot legally omit one', function () {
        // The SDK's ObjectSchema sets `additionalProperties: false` and nothing
        // else, so without `required` a model may answer `{"topics": [...]}` and
        // drop the rest - which it did: a four-field request returned one field
        // and the others were recorded as failed. Nothing surfaced it, because
        // the instructions separately invite the model to omit a field it has
        // nothing for, so an omission read as an honest gap.
        //
        // Asserted on the schema as it reaches the wire, not on the agent's own
        // return, because the agent's shape is not what Ollama is sent: the SDK
        // wraps it and derives `required` from the per-property flags.
        $wire = (new ObjectSchema(
            (new MeetingSummaryAgent)->schema(new JsonSchemaTypeFactory),
        ))->toSchema();

        expect($wire['required'] ?? null)->toBe([
            'executive_summary',
            'topics',
            'bullet_points',
            'key_decisions',
            'suggested_tags',
        ]);
    });

    it('requires only the fields the run asked for', function () {
        // A narrowed schema that still required everything would push the model
        // to invent content for fields nobody asked about.
        $wire = (new ObjectSchema(
            (new MeetingSummaryAgent)
                ->withFields(['key_decisions', 'suggested_tags'])
                ->schema(new JsonSchemaTypeFactory),
        ))->toSchema();

        expect($wire['required'] ?? null)->toBe(['key_decisions', 'suggested_tags']);
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

describe('the context window', function () {
    it('asks the local runtime for a window the whole prompt fits in', function () {
        // The failure this guards against is invisible from the outside.
        // Ollama's default window is 4096 tokens and it reserves half of that
        // for the answer, so a 60k-character prompt was reaching the model
        // truncated to ~2000 tokens — about a fifth of the minutes — and it
        // still returned a fluent, well-formed summary of a document it had
        // mostly not seen. No error, no partial answer, no signal anywhere:
        // just a confident summary of the wrong amount of text.
        //
        // Asserted on the wire rather than on the method that returns the
        // options, because the wiring is the part that can silently stop
        // working: `HasProviderOptions` is only honoured when the SDK builds
        // its options from the agent, and that is not this code's call.
        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:11434/*' => Http::response([
                'model' => 'gemma3:12b',
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode(['executive_summary' => 'خلاصه'], JSON_UNESCAPED_UNICODE),
                ],
                'done' => true,
                'done_reason' => 'stop',
                'prompt_eval_count' => 11022,
                'eval_count' => 52,
            ]),
        ]);

        seedSummaryModel('local', 'gemma3:12b');

        app(AiAgentRunner::class)->run(
            key: MeetingSummaryAgent::REGISTRY_KEY,
            prompt: 'go',
            agentClass: MeetingSummaryAgent::class,
            settings: ['fields' => ['executive_summary'], 'timeout' => 5],
        );

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return str_contains($request->url(), '/api/chat')
                && data_get($body, 'options.num_ctx') === MeetingSummaryAgent::CONTEXT_TOKENS
                && data_get($body, 'keep_alive') === MeetingSummaryAgent::KEEP_ALIVE;
        });
    });

    it('keeps those knobs away from a runtime that does not have them', function () {
        // A cloud provider handed `num_ctx` would either reject the request
        // or ignore it, and finding out which during a multi-minute run is a
        // poor way to learn it.
        expect((new MeetingSummaryAgent)->providerOptions(Lab::OpenAI))->toBe([])
            ->and((new MeetingSummaryAgent)->providerOptions('openai-compatible'))->toBe([]);
    });

    it('keeps the window above the size the real prompt proved it needs', function () {
        // The other test asserts the option reaches the wire but reads the
        // constant, so it passes unchanged if the constant is lowered back
        // into the range where truncation happens. This one puts the floor
        // in numbers: a real four-field run measured 11530 input tokens, and
        // Ollama reserves half the window for the answer, so anything under
        // 23060 drops the minutes back to the fifth of themselves that this
        // whole change exists to stop.
        //
        // The floor is asserted rather than the value 32768 itself because
        // 32768 is a choice - a power of two, with room to spare - and 23060
        // is a measurement. Only the measurement is a fact to hold onto.
        expect(MeetingSummaryAgent::CONTEXT_TOKENS)->toBeGreaterThanOrEqual(23060);
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

        expect(app(SystemSettingsRepositoryInterface::class)->get(
            AiAgent::class,
            AiRegistryKey::AGENTS.MeetingSummaryAgent::REGISTRY_KEY,
        ))->toBeNull();

        $summary = $this->summarizer->summarize($this->meeting, ['executive_summary']);

        expect($summary->status)->toBe(MeetingSummary::STATUS_OK)
            ->and($summary->toArray()['agent'])->toBe(MeetingSummaryAgent::REGISTRY_KEY);
    });

    it('prefers the registry row once one exists', function () {
        // Otherwise "create a row in Settings" would be a change that silently
        // does nothing until a deploy, which is the opposite of D28's promise.
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        app(SystemSettingsRepositoryInterface::class)->put(
            new AiAgent(
                MeetingSummaryAgent::REGISTRY_KEY,
                'Moulid khalase',
                MeetingSummaryAgent::class,
            ),
        );

        expect($this->summarizer->summarize($this->meeting, ['executive_summary'])->status)
            ->toBe(MeetingSummary::STATUS_OK);

        expect(app(SystemSettingsRepositoryInterface::class)->get(
            AiAgent::class,
            AiRegistryKey::AGENTS.MeetingSummaryAgent::REGISTRY_KEY,
        ))->not->toBeNull();
    });
});

describe('the job', function () {
    it('stores the artifact on the meeting', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]]);

        runSummaryJob($this->meeting->id, ['executive_summary', 'topics']);

        $stored = $this->meeting->fresh()->summary;

        expect($stored['executive_summary'])->toBe('خلاصه')
            ->and($stored['topics'])->toBe(['بودجه'])
            ->and($stored['status'])->toBe(MeetingSummary::STATUS_OK);
    });

    it('marks the meeting pending while the model is still running', function () {
        MeetingSummaryAgent::fake(function (): array {
            // Read inside the run: a bulk action leaves most of the registry
            // in flight at once, and "queued" must not look like "never asked".
            $running = $this->meeting->fresh()->summary;

            expect($running['status'])->toBe(MeetingSummary::STATUS_PENDING)
                // Under `Apply` the run is the row, so its start time sits at
                // the top — the only start time a reloaded tab can count from.
                ->and(abs(time() - strtotime($running['started_at'])))->toBeLessThan(60);

            return ['executive_summary' => 'خلاصه'];
        });

        runSummaryJob($this->meeting->id, ['executive_summary']);

        expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_OK)
            ->and($this->meeting->fresh()->summary['started_at'])->toBeNull();
    });

    it('stamps when the run began, alongside the stamp itself', function () {
        MeetingSummaryAgent::fake(function (): array {
            // A refresh or a second tab has never seen a progress event, so
            // the start time it counts from is this one. Under `Review` the
            // run *is* the candidate - the record's own content is left alone
            // on purpose - so the date lives there rather than at the top.
            $started = $this->meeting->fresh()->summary['pending']['started_at'];

            expect($started)->toBeString()
                ->and(abs(time() - strtotime($started)))->toBeLessThan(60);

            return ['executive_summary' => 'خلاصه'];
        });

        runSummaryJob(
            $this->meeting->id,
            ['executive_summary'],
            SummaryWriteMode::Review,
        );

        // Cleared with the stamp: a row whose run has reported has no run
        // under way, and a date saying otherwise would make the next reader
        // count from a run that is over.
        expect($this->meeting->fresh()->summary['pending']['started_at'])->toBeNull();
    });

    it('leaves a human-owned meeting unlocked', function () {
        $locked = BoardMeeting::factory()->humanOwned()->create(['source_locked_at' => $stamp = now()]);
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($locked->id, ['executive_summary']);

        // A summary is regenerated on a locked meeting on purpose — that is
        // the whole point of being able to regenerate it — so generating must
        // never freeze the source's view of the row.
        expect($locked->fresh()->source_locked_at?->toDateTimeString())->toBe($stamp->toDateTimeString());
    });

    it('does nothing when the meeting is gone', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob(999999, ['executive_summary']);

        MeetingSummaryAgent::assertNeverPrompted();
    });

    it('records a permanent failure instead of retrying forever', function () {
        // No provider at all: the run cannot start, and no number of retries
        // will conjure one.
        $this->meeting->forceFill(['summary' => null])->save();
        app(SystemSettingsRepositoryInterface::class)->forget(AiProvider::class, AiRegistryKey::PROVIDERS.'local');
        app(AiProviderResolver::class)->forget();

        runSummaryJob($this->meeting->id, ['executive_summary']);

        expect($this->meeting->fresh()->summary)
            ->toMatchArray(['status' => MeetingSummary::STATUS_FAILED, 'error' => MeetingSummary::REASON_NOT_CONFIGURED]);
    });

    it('lets a transient failure reach the queue', function () {
        // A dead URL or a rejected key is worth retrying, so it must not be
        // swallowed into a written `failed`.
        MeetingSummaryAgent::fake(fn () => throw new RuntimeException('connection reset'));

        expect(fn () => runSummaryJob($this->meeting->id, ['executive_summary']))
            ->toThrow(RuntimeException::class);

        expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_PENDING);
    });

    it('writes the outcome once the queue has given up on the run', function () {
        // The test above describes what happens *between* attempts, and it is
        // right that the row stays pending there. This is the last attempt.
        //
        // Without it an abandoned run keeps its `pending` stamp for ever, so
        // "the gateway timed out" and "the model is still thinking" read
        // identically - the exact confusion the pending stamp was introduced to
        // prevent, reached from the other side. The worker retries nothing by
        // default, so in practice this is every timeout.
        $this->meeting->forceFill(['summary' => MeetingSummary::pending()->toArray()])->save();

        $job = new GenerateBoardMeetingSummaryJob(
            $this->meeting->id,
            ['executive_summary'],
            mode: SummaryWriteMode::Apply,
            requestedBy: 4242,
        );

        $job->failed(new RuntimeException('cURL error 28: Operation timed out'));

        expect($this->meeting->fresh()->summary)
            ->toMatchArray([
                'status' => MeetingSummary::STATUS_FAILED,
                'error' => MeetingSummary::REASON_UNAVAILABLE,
            ]);
    });

    it('says nothing when the meeting was deleted while the run was in flight', function () {
        // Deleting the meeting is not a reason for the failure handler to
        // throw: there is no row to write to and no subject to report about,
        // and a second exception inside the failure path would only replace a
        // recorded failure with an unrecorded one.
        Event::fake([RunProgressed::class, MeetingSummaryGenerated::class]);

        (new GenerateBoardMeetingSummaryJob(999999, ['executive_summary']))
            ->failed(new RuntimeException('gateway refused'));

        Event::assertNotDispatched(RunProgressed::class);
        Event::assertNotDispatched(MeetingSummaryGenerated::class);
    });
});

describe('run progress', function () {
    it('reports running then finished to the requester', function () {
        // The run is what tells the waiting person it moved, so the sequence is
        // the contract: `running` once the model is actually being called, then
        // a terminal state. A client that only ever saw `finished` would sit on
        // a spinner with nothing between "asked" and "done".
        Event::fake([RunProgressed::class]);

        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary'], requestedBy: 4242);

        Event::assertDispatchedTimes(RunProgressed::class, 2);

        Event::assertDispatched(RunProgressed::class, fn (RunProgressed $e): bool => $e->state === RunState::Running
            && $e->userId === 4242);

        Event::assertDispatched(RunProgressed::class, fn (RunProgressed $e): bool => $e->state === RunState::Finished
            && $e->context === ['status' => MeetingSummary::STATUS_OK]);
    });

    it('reports a permanent failure as failed rather than finished', function () {
        // The distinction the client draws its whole reaction on: a finished run
        // and a failed one are different sentences, and both are terminal.
        Event::fake([RunProgressed::class]);

        $this->meeting->forceFill(['summary' => null])->save();
        app(SystemSettingsRepositoryInterface::class)->forget(AiProvider::class, AiRegistryKey::PROVIDERS.'local');
        app(AiProviderResolver::class)->forget();

        runSummaryJob($this->meeting->id, ['executive_summary'], requestedBy: 4242);

        Event::assertDispatched(RunProgressed::class, fn (RunProgressed $e): bool => $e->state === RunState::Failed
            && $e->context === ['reason' => MeetingSummary::REASON_NOT_CONFIGURED]);
    });

    it('reports an abandoned run as failed and announces it', function () {
        // Two facts a waiting person needs and would otherwise never get: the
        // run stopped, and it stopped for a reason that is not "you misconfigured
        // the hub". The announcement is what makes the notification reachable
        // at all - a run the queue abandoned used to fire no event, so it had
        // no progress, no audit row and no message to anybody.
        Event::fake([RunProgressed::class, MeetingSummaryGenerated::class]);

        $job = new GenerateBoardMeetingSummaryJob(
            $this->meeting->id,
            ['executive_summary'],
            requestedBy: 4242,
        );

        $job->failed(new RuntimeException('cURL error 28: Operation timed out'));

        Event::assertDispatched(RunProgressed::class, fn (RunProgressed $e): bool => $e->state === RunState::Failed
            && $e->userId === 4242
            && $e->context === ['reason' => MeetingSummary::REASON_UNAVAILABLE]
            && $e->subject?->id === $this->meeting->id);

        Event::assertDispatched(MeetingSummaryGenerated::class, fn (MeetingSummaryGenerated $e): bool => $e->requestedBy() === 4242
            && $e->metadata()['status'] === MeetingSummary::STATUS_FAILED
            && $e->metadata()['error'] === MeetingSummary::REASON_UNAVAILABLE);
    });

    it('names the client cache key rather than the run itself', function () {
        // The channel is the client's cache prefix, so it has to be the one the
        // meeting's queries actually live under - otherwise the event arrives
        // and invalidates nothing, and the feature is silently dead while every
        // assertion about "an event was sent" still passes.
        Event::fake([RunProgressed::class]);

        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary'], requestedBy: 4242);

        Event::assertDispatched(RunProgressed::class, fn (RunProgressed $e): bool => $e->channel === 'board-meetings');
    });

    it('names the meeting, so a bulk run tells its runs apart', function () {
        // Ten of these jobs share one cache prefix, so without a subject a client
        // watching the bulk dialog would see "something moved" and could not show
        // which row went running - which is the whole reason the dialog stays
        // open. Asserted on every state, because a subject that only the terminal
        // event carried would leave the queued and running rows indistinguishable.
        Event::fake([RunProgressed::class]);

        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary'], requestedBy: 4242);

        Event::assertDispatchedTimes(RunProgressed::class, 2);

        Event::assertDispatched(RunProgressed::class, fn (RunProgressed $e): bool => $e->subject?->type === 'board-meeting'
            && $e->subject->id === $this->meeting->id
            && $e->state === RunState::Running);

        Event::assertDispatched(RunProgressed::class, fn (RunProgressed $e): bool => $e->subject?->id === $this->meeting->id
            && $e->state === RunState::Finished);
    });

    it('reports nothing when the run has no requester', function () {
        // The scheduled/CLI shape. Reporting to nobody must be silent rather
        // than broken - the outcome is written to the row either way.
        Event::fake([RunProgressed::class]);

        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary']);

        Event::assertNotDispatched(RunProgressed::class);
    });

    it('still writes the outcome when there is no requester to tell', function () {
        // The regression that matters most: progress is a courtesy, and adding
        // it must not make the run depend on somebody being subscribed.
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary']);

        expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_OK);
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

        runSummaryJob($this->meeting->id, ['key_decisions']);

        $stored = $this->meeting->fresh()->summary;

        expect($stored['key_decisions'])->toBe(['مصوبهٔ ۴۲'])
            ->and($stored['executive_summary'])->toBe('خلاصهٔ قبلی')
            ->and($stored['topics'])->toBe(['بودجه'])
            // The stored tags round-trip through the pair shape: a legacy
            // `['budget']` is read as name-and-slug and written back that way.
            ->and($stored['suggested_tags'])->toBe([
                ['name' => 'budget', 'slug' => 'budget'],
            ]);
    });

    it('keeps the old answer when the regeneration comes back empty', function () {
        MeetingSummaryAgent::fake([['key_decisions' => []]]);

        runSummaryJob($this->meeting->id, ['key_decisions']);

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

        runSummaryJob($this->meeting->id, ['key_decisions']);

        expect($this->meeting->fresh()->summary['executive_summary'])->toBe('خلاصهٔ قبلی');
    });

    it('names the model that wrote the most recent thing written', function () {
        MeetingSummaryAgent::fake([['key_decisions' => ['مصوبهٔ ۴۲']]]);

        runSummaryJob($this->meeting->id, ['key_decisions']);

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

    it('keeps a different mode a different request', function () {
        // "Put it in the record" and "hold it for me" are two things to ask for.
        // Dropping the second because the first was in flight would hand someone
        // a proposal when they asked for an answer - the exact surprise the mode
        // exists to prevent.
        expect((new GenerateBoardMeetingSummaryJob(7, ['topics'], null, null, SummaryWriteMode::Apply))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(7, ['topics'], null, null, SummaryWriteMode::Review))->uniqueId());
    });

    it('keeps a different model a different request', function () {
        // D26's promise again: asking twice on purpose with two models is a
        // comparison, so the lock must not swallow the second.
        expect((new GenerateBoardMeetingSummaryJob(7, ['topics'], 'local', 'llama3.2'))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(7, ['topics'], 'local', 'gpt-4o'))->uniqueId());
    });

    it('keeps the same model behind two gateways two different requests', function () {
        // A model name is unique only within a provider, so the pair is the
        // identity. Collapsing these would drop a paid run.
        expect((new GenerateBoardMeetingSummaryJob(7, ['topics'], 'local', 'gpt-4o'))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(7, ['topics'], 'gateway', 'gpt-4o'))->uniqueId());
    });

    it('does not treat an inherited model as a named one', function () {
        // "Whatever this run would have used anyway" and "this model, on the
        // provider that was going to run it" produce the same run, so the lock
        // may collapse them - but naming a provider is not the same request as
        // naming nothing, and must not be mistaken for it.
        expect((new GenerateBoardMeetingSummaryJob(7, ['topics'], null, 'llama3.2'))->uniqueId())
            ->not->toBe((new GenerateBoardMeetingSummaryJob(7, ['topics'], 'local', 'llama3.2'))->uniqueId());
    });

    it('is implemented as a unique job', function () {
        expect(new GenerateBoardMeetingSummaryJob(7))->toBeInstanceOf(ShouldBeUnique::class);
    });
});

describe('the stored shape with a proposal in it', function () {
    it('always carries the key, so "nothing awaiting a decision" is readable', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary']);

        // A reader must not have to tell "nothing pending" from "an older shape
        // that had no pending key at all" — the two would be the same absent
        // array to anyone who wrote `summary['pending'] ?? []`.
        expect($this->meeting->fresh()->summary)->toHaveKey('pending')
            ->and($this->meeting->fresh()->summary['pending'])->toBeNull();
    });

    it('reads a proposal back as the same shape it was written as', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه', 'topics' => ['بودجه']]]);

        runSummaryJob($this->meeting->id, ['executive_summary', 'topics'], SummaryWriteMode::Review);

        $round = MeetingSummary::fromArray($this->meeting->fresh()->summary);

        expect($round->pending)->not->toBeNull()
            ->and($round->pending->status)->toBe(MeetingSummary::STATUS_OK)
            ->and($round->pending->values)->toBe(['executive_summary' => 'خلاصه', 'topics' => ['بودجه']])
            ->and($round->pending->toArray())->toBe($round->pending->toArray());
    });

    it('drops a proposal nested inside a proposal', function () {
        $round = MeetingSummary::fromArray([
            'status' => 'none',
            'pending' => [
                'status' => 'ok',
                'executive_summary' => 'خلاصه',
                'pending' => ['status' => 'ok', 'topics' => ['بودجه']],
            ],
        ]);

        // Nothing writes one, so following it would let a hand-edited row claim
        // a queue of proposals that has no meaning.
        expect($round->pending->values)->toBe(['executive_summary' => 'خلاصه'])
            ->and($round->pending->pending)->toBeNull();
    });

    it('calls a meeting with a proposal nobody looked at "none", not "failed"', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary'], SummaryWriteMode::Review);

        $stored = $this->meeting->fresh()->summary;

        // The record holds no content and has not failed — the output is
        // waiting for a person. Reporting `failed` would tell a user their
        // summary is broken when it is sitting in the tab under their cursor.
        expect($stored['status'])->toBe(MeetingSummary::STATUS_NONE)
            ->and($stored)->not->toHaveKey('executive_summary')
            ->and($stored['pending']['status'])->toBe(MeetingSummary::STATUS_OK);
    });
});

describe('review mode', function () {
    it('is what the job does when nobody says otherwise', function () {
        // The direction to fail in: a caller that forgets the mode leaves the
        // record alone instead of overwriting content somebody is relying on.
        // Built by hand rather than through the helper, because the helper is
        // the thing that spells `Apply` out for the tests above.
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        (new GenerateBoardMeetingSummaryJob($this->meeting->id, ['executive_summary']))
            ->handle(app(BoardMeetingSummarizer::class), app(MeetingSummaryApplier::class));

        $stored = $this->meeting->fresh()->summary;

        expect($stored)->not->toHaveKey('executive_summary')
            ->and($stored['pending']['executive_summary'])->toBe('خلاصه');
    });

    it('leaves a working summary exactly as it was', function () {
        $this->meeting->forceFill(['summary' => [
            'status' => MeetingSummary::STATUS_OK,
            'executive_summary' => 'خلاصهٔ قبلی',
            'fields' => ['executive_summary' => ['status' => 'ok', 'generated_at' => '2026-09-01T10:00:00+00:00']],
            'generated_at' => '2026-09-01T10:00:00+00:00',
        ]])->save();
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصهٔ تازه']]);

        runSummaryJob($this->meeting->id, ['executive_summary'], SummaryWriteMode::Review);

        $stored = $this->meeting->fresh()->summary;

        // Not even the status moves: a proposal arriving must not make a
        // working summary look like it is being rewritten under the reader.
        expect($stored['executive_summary'])->toBe('خلاصهٔ قبلی')
            ->and($stored['status'])->toBe(MeetingSummary::STATUS_OK)
            ->and($stored['pending']['executive_summary'])->toBe('خلاصهٔ تازه');
    });

    it('shows the run in flight as a pending proposal', function () {
        MeetingSummaryAgent::fake(function (): array {
            expect($this->meeting->fresh()->summary['pending']['status'])->toBe(MeetingSummary::STATUS_PENDING);

            return ['executive_summary' => 'خلاصه'];
        });

        runSummaryJob($this->meeting->id, ['executive_summary'], SummaryWriteMode::Review);

        expect($this->meeting->fresh()->summary['pending']['status'])->toBe(MeetingSummary::STATUS_OK);
    });

    it('replaces an undecided proposal rather than stacking a second one', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'اول']]);

        runSummaryJob($this->meeting->id, ['executive_summary'], SummaryWriteMode::Review);

        MeetingSummaryAgent::fake([['executive_summary' => 'دوم']]);
        runSummaryJob($this->meeting->id, ['executive_summary'], SummaryWriteMode::Review);

        $round = MeetingSummary::fromArray($this->meeting->fresh()->summary);

        expect($round->pending->values['executive_summary'])->toBe('دوم');
    });

    it('drops an undecided proposal when a later run is applied', function () {
        MeetingSummaryAgent::fake([['executive_summary' => 'پیشنهاد']]);

        runSummaryJob($this->meeting->id, ['executive_summary'], SummaryWriteMode::Review);

        MeetingSummaryAgent::fake([['topics' => ['بودجه']]]);
        runSummaryJob($this->meeting->id, ['topics'], SummaryWriteMode::Apply);

        // The applied run answers the question the proposal was waiting on, so
        // leaving it on the row would ask the person to decide about output that
        // has already been superseded.
        expect($this->meeting->fresh()->summary['pending'])->toBeNull();
    });
});

describe('a person deciding about a proposal', function () {
    beforeEach(function () {
        MeetingSummaryAgent::fake([[
            'executive_summary' => 'خلاصهٔ تازه',
            'topics' => ['بودجه'],
            'key_decisions' => ['مصوبهٔ ۴۲'],
        ]]);

        $this->meeting->forceFill(['summary' => [
            'status' => MeetingSummary::STATUS_OK,
            'executive_summary' => 'خلاصهٔ قبلی',
            'topics' => ['قرارداد'],
            'fields' => [
                'executive_summary' => ['status' => 'ok', 'generated_at' => '2026-09-01T10:00:00+00:00'],
                'topics' => ['status' => 'ok', 'generated_at' => '2026-09-01T10:00:00+00:00'],
            ],
            'generated_at' => '2026-09-01T10:00:00+00:00',
        ]])->save();

        runSummaryJob($this->meeting->id, ['executive_summary', 'topics', 'key_decisions'], SummaryWriteMode::Review);

        // The applier reads the summary off the model it is handed, and the
        // job wrote through a model of its own — so the test has to hand it a
        // re-read one, exactly as the controller does.
        $this->meeting->refresh();

        $this->applier = app(MeetingSummaryApplier::class);
    });

    it('takes the fields it was given and leaves the rest of the record alone', function () {
        $this->applier->acceptPending($this->meeting, [
            MeetingSummaryField::ExecutiveSummary,
            MeetingSummaryField::KeyDecisions,
        ], []);

        $stored = $this->meeting->fresh()->summary;

        expect($stored['executive_summary'])->toBe('خلاصهٔ تازه')
            ->and($stored['key_decisions'])->toBe(['مصوبهٔ ۴۲'])
            ->and($stored['topics'])->toBe(['قرارداد'])
            ->and($stored['status'])->toBe(MeetingSummary::STATUS_OK);
    });

    it('stops offering the proposal', function () {
        $this->applier->acceptPending($this->meeting, [MeetingSummaryField::Topics], []);

        expect($this->meeting->fresh()->summary['pending'])->toBeNull();
    });

    it('derives the row status from the accepted fields, not the whole run', function () {
        $this->applier->acceptPending($this->meeting, [MeetingSummaryField::KeyDecisions], []);

        // One field accepted out of three, and it came back filled — the row
        // holds a key decision and the old summary, so it is ok. Deriving from
        // the run instead would have counted fields the person refused.
        expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_OK);
    });

    it('is happy to set tags without touching any content', function () {
        $this->applier->acceptPending($this->meeting, [], ['budget', 'tender']);

        $stored = $this->meeting->fresh()->summary;

        expect($stored['executive_summary'])->toBe('خلاصهٔ قبلی')
            ->and($stored['pending'])->toBeNull()
            ->and($this->meeting->fresh()->tags->pluck('slug')->all())
            ->toEqualCanonicalizing(['budget', 'tender']);
    });

    it('treats an empty decision as having read and rejected the proposal', function () {
        app(TagService::class)->syncTags($this->meeting, ['budget']);

        $this->applier->acceptPending($this->meeting, [], ['budget']);

        $stored = $this->meeting->fresh()->summary;

        // There is no "dismissed" marker, because a dismissed proposal and one
        // that was never made look the same from here — which is the truth, and
        // why the only way to get another is to run again.
        expect($stored['pending'])->toBeNull()
            ->and($stored['executive_summary'])->toBe('خلاصهٔ قبلی')
            ->and($this->meeting->fresh()->tags->pluck('slug')->all())->toBe(['budget']);
    });

    it('replaces the tag set rather than adding to it', function () {
        app(TagService::class)->syncTags($this->meeting, ['budget', 'obsolete']);

        $this->applier->acceptPending($this->meeting, [], ['budget', 'tender']);

        expect($this->meeting->fresh()->tags->pluck('slug')->all())
            ->toEqualCanonicalizing(['budget', 'tender']);
    });

    it('keeps a stored value when the accepted field came back empty', function () {
        $this->meeting->forceFill(['summary' => [
            'status' => MeetingSummary::STATUS_NONE,
            'pending' => [
                'status' => MeetingSummary::STATUS_PARTIAL,
                'topics' => [],
                'fields' => [
                    'topics' => [
                        'status' => MeetingSummary::FIELD_FAILED,
                        'error' => MeetingSummary::REASON_EMPTY,
                        'generated_at' => null,
                    ],
                ],
            ],
        ]])->save();

        $this->applier->acceptPending($this->meeting, [MeetingSummaryField::Topics], []);

        $stored = $this->meeting->fresh()->summary;

        // Ticking a field the run left empty is harmless rather than
        // destructive: it merges as a failed field, and a failed field keeps
        // what the record already had. The record still has nothing usable in
        // it, and says so — the tick did not invent content.
        expect($stored['status'])->toBe(MeetingSummary::STATUS_FAILED)
            ->and($stored['fields']['topics']['status'])->toBe(MeetingSummary::FIELD_FAILED)
            ->and($stored['fields']['topics']['error'])->toBe(MeetingSummary::REASON_EMPTY);
    });

    it('refuses a decision about a proposal that is not there', function () {
        $this->applier->acceptPending($this->meeting, [MeetingSummaryField::Topics], []);

        expect(fn () => $this->applier->acceptPending($this->meeting, [MeetingSummaryField::Topics], []))
            ->toThrow(NoPendingSummary::class);
    });
});

describe('tags following a run', function () {
    it('are set by a run that re-derived them', function () {
        MeetingSummaryAgent::fake([['suggested_tags' => ['budget', 'tender']]]);

        runSummaryJob($this->meeting->id, ['suggested_tags']);

        expect($this->meeting->fresh()->tags->pluck('slug')->all())
            ->toEqualCanonicalizing(['budget', 'tender']);
    });

    it('are left alone by a run that was not asked about them', function () {
        // D25 routes the run's suggestions through the hub's own tagging
        // service, which has full-sync semantics: it detaches anything not in
        // the list it is given. A `key_decisions` regeneration has no opinions
        // about tags, so passing it the old list would replace a tag set
        // nobody asked to replace.
        app(TagService::class)->syncTags($this->meeting, ['budget']);
        MeetingSummaryAgent::fake([['key_decisions' => ['مصوبهٔ ۴۲']]]);

        runSummaryJob($this->meeting->id, ['key_decisions']);

        expect($this->meeting->fresh()->tags->pluck('slug')->all())->toBe(['budget']);
    });

    it('are left alone when the run came back without them', function () {
        app(TagService::class)->syncTags($this->meeting, ['budget']);
        MeetingSummaryAgent::fake([['suggested_tags' => []]]);

        runSummaryJob($this->meeting->id, ['suggested_tags']);

        // The previous suggestions still stand. Re-syncing them would undo any
        // retagging done through the hub's own tag screen since.
        expect($this->meeting->fresh()->tags->pluck('slug')->all())->toBe(['budget']);
    });

    it('are not touched at all in review mode', function () {
        // In review mode the run has not been accepted, so its tag suggestions
        // are a proposal like everything else — writing them would be the
        // feature deciding on the person's behalf.
        app(TagService::class)->syncTags($this->meeting, ['budget']);
        MeetingSummaryAgent::fake([['suggested_tags' => ['tender']]]);

        runSummaryJob($this->meeting->id, ['suggested_tags'], SummaryWriteMode::Review);

        expect($this->meeting->fresh()->tags->pluck('slug')->all())->toBe(['budget']);
    });
});

/*
 * The tag half of the prompt (D20/D25): a model can only reuse a vocabulary it
 * was shown, and a proposed label only becomes a reused tag if something maps
 * it back. These two halves are what turn "the model wrote Persian we can read"
 * into "the meeting carries the hub's own tags".
 */
describe('tag proposals and the hub vocabulary', function () {
    it('shows a tag run the labels the hub already uses, and only that run', function () {
        Tag::create(['name' => 'شرکت سمتک', 'slug' => 'samtak-company']);

        $withTags = $this->summarizer->promptFor($this->meeting, [], true);
        $withoutTags = $this->summarizer->promptFor($this->meeting);

        expect($withTags)->toContain('KNOWN TAGS')
            ->and($withTags)->toContain('شرکت سمتک')
            ->and($withoutTags)->not->toContain('KNOWN TAGS');
    });

    it('asks for a Persian label and a latin key, never a bare slug', function () {
        // The old wording asked for "subject slugs", and on a Persian hub that
        // is why a tags-only run came back with English words where a person
        // expected Persian labels.
        $instructions = (new MeetingSummaryAgent(['suggested_tags']))->instructions();

        expect($instructions)->toContain('`name` is the label written in Persian')
            ->and($instructions)->toContain('`slug`')
            // A run that was not asked for tags is not told about a list it
            // will never be shown.
            ->and((new MeetingSummaryAgent(['topics']))->instructions())
            ->not->toContain('KNOWN TAGS');
    });

    it('reuses an existing tag when the run names it, whatever key it guessed', function () {
        Tag::create(['name' => 'شرکت سمتک', 'slug' => 'samtak-company']);

        MeetingSummaryAgent::fake([[
            'suggested_tags' => [
                ['name' => 'شرکت سمتک', 'slug' => 'samtak'],
            ],
        ]]);

        runSummaryJob($this->meeting->id, ['suggested_tags']);

        $stored = $this->meeting->fresh()->summary;

        expect($stored['suggested_tags'])->toBe([
            ['name' => 'شرکت سمتک', 'slug' => 'samtak-company'],
        ])->and($this->meeting->fresh()->tags->pluck('slug')->all())
            ->toBe(['samtak-company'])
            // The guessed key never became a second tag for one company.
            ->and(Tag::where('slug', 'samtak')->exists())->toBeFalse();
    });

    it('keeps the Persian label and makes a safe latin key for a new tag', function () {
        MeetingSummaryAgent::fake([[
            'suggested_tags' => [
                ['name' => 'آموزش رایگان', 'slug' => 'Free Training'],
            ],
        ]]);

        runSummaryJob($this->meeting->id, ['suggested_tags']);

        $tag = Tag::where('slug', 'free-training')->first();

        expect($tag)->not->toBeNull()
            ->and($tag->name)->toBe('آموزش رایگان')
            ->and($this->meeting->fresh()->tags->pluck('slug')->all())
            ->toBe(['free-training']);
    });
});

describe('the audit trail', function () {
    it('records one event per run, describing the run', function () {
        Event::fake([MeetingSummaryGenerated::class]);
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary']);

        Event::assertDispatched(MeetingSummaryGenerated::class, function (MeetingSummaryGenerated $event): bool {
            $metadata = $event->metadata();

            return $event->eventName() === 'boardmeeting.summary_generated'
                && $event->subject() === ['type' => 'board-meeting', 'id' => $this->meeting->id]
                && $metadata['mode'] === SummaryWriteMode::Apply->value
                && $metadata['status'] === MeetingSummary::STATUS_OK
                && $metadata['requested_fields'] === ['executive_summary']
                && $metadata['filled_fields'] === ['executive_summary']
                && $metadata['error'] === null;
        });
    });

    it('says which mode the run ran in, because that is what it did', function () {
        Event::fake([MeetingSummaryGenerated::class]);
        MeetingSummaryAgent::fake([['executive_summary' => 'خلاصه']]);

        runSummaryJob($this->meeting->id, ['executive_summary'], SummaryWriteMode::Review);

        Event::assertDispatched(
            MeetingSummaryGenerated::class,
            fn (MeetingSummaryGenerated $event): bool => $event->metadata()['mode'] === 'review',
        );
    });

    it('records a run that produced nothing usable', function () {
        Event::fake([MeetingSummaryGenerated::class]);
        $this->meeting->forceFill(['summary' => null])->save();
        app(SystemSettingsRepositoryInterface::class)->forget(AiProvider::class, AiRegistryKey::PROVIDERS.'local');
        app(AiProviderResolver::class)->forget();

        runSummaryJob($this->meeting->id, ['executive_summary']);

        // "We asked and here is what it said" is the fact the registry's
        // history has to be able to answer, and no-fields is still an answer.
        Event::assertDispatched(
            MeetingSummaryGenerated::class,
            fn (MeetingSummaryGenerated $event): bool => $event->metadata()['error'] === MeetingSummary::REASON_NOT_CONFIGURED,
        );
    });

    it('leaves no row for a run that never reported', function () {
        Event::fake([MeetingSummaryGenerated::class]);
        MeetingSummaryAgent::fake(fn () => throw new RuntimeException('connection reset'));

        expect(fn () => runSummaryJob($this->meeting->id, ['executive_summary']))
            ->toThrow(RuntimeException::class);

        // D22 asks for an event per *run*, and a run still being retried has
        // not finished. The meeting keeps saying `pending` until one does.
        Event::assertNotDispatched(MeetingSummaryGenerated::class);
        expect($this->meeting->fresh()->summary['status'])->toBe(MeetingSummary::STATUS_PENDING);
    });
});
