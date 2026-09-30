<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Models\AiProvider;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSummarizer;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

/*
 * A reconciling run: the model is also handed the text a person already
 * accepted, and asked to improve it rather than to start over.
 *
 * The risk this feature introduces is a lazy answer, not a wrong one — a model
 * handed an existing draft in the prompt will happily echo it back. So most of
 * what follows is about what the run is *not* told: a fresh run must not see
 * the stored text at all, and a merge must not carry fields nobody asked about.
 */

/** A stored artifact holding one field, as an accepted record would. */
function acceptedSummary(array $values): array
{
    $fields = [];

    foreach (array_keys($values) as $name) {
        $fields[$name] = [
            'status' => 'ok',
            'error' => null,
            'generated_at' => '2026-09-01T10:00:00+00:00',
        ];
    }

    return [
        'status' => MeetingSummary::STATUS_OK,
        ...$values,
        'fields' => $fields,
        'agent' => 'board-meeting.summary',
        'provider' => 'local',
        'model' => 'llama3.2',
        'usage' => null,
        'error' => null,
        'generated_at' => '2026-09-01T10:00:00+00:00',
        'pending' => null,
    ];
}

beforeEach(function () {
    AiProvider::factory()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);

    $this->summarizer = app(BoardMeetingSummarizer::class);
    $this->meeting = BoardMeeting::factory()->create([
        'title' => 'جلسهٔ شورا',
        'minutes' => 'مصوبهٔ شمارهٔ ۴۱ تصویب شد.',
    ]);
});

describe('the prompt a run is given', function () {
    beforeEach(function () {
        $this->meeting->forceFill(['summary' => acceptedSummary([
            'executive_summary' => 'خلاصهٔ قبلی',
            'key_decisions' => ['مصوبهٔ ۴۱'],
        ])])->save();

        $this->meeting->refresh();
    });

    it('never mentions the stored record on an ordinary run', function () {
        // A fresh run must not even hint that an accepted draft exists. If it
        // did, the model would start treating the stored text as something to
        // preserve, and "regenerate this field" would quietly become "polish
        // this field" — two different requests behind one button.
        expect($this->summarizer->promptFor($this->meeting))
            ->not->toContain('CURRENT RECORD')
            ->and($this->summarizer->promptFor($this->meeting))
            ->not->toContain('خلاصهٔ قبلی');
    });

    it('shows the accepted text when merging', function () {
        $prompt = $this->summarizer->promptFor($this->meeting, [
            'executive_summary' => 'خلاصهٔ قبلی',
        ]);

        expect($prompt)->toContain('CURRENT RECORD')
            ->and($prompt)->toContain('خلاصهٔ قبلی');
    });

    it('keeps the minutes in the prompt alongside the record', function () {
        $prompt = $this->summarizer->promptFor($this->meeting, [
            'executive_summary' => 'خلاصهٔ قبلی',
        ]);

        // Reconciling is a comparison, and a comparison needs both sides. A
        // prompt with the old draft and no minutes would produce a rewrite of
        // the old draft.
        expect($prompt)->toContain('Minutes:')
            ->and($prompt)->toContain('مصوبهٔ شمارهٔ ۴۱');
    });

    it('writes a list field as one line, so the model can read it back', function () {
        $prompt = $this->summarizer->promptFor($this->meeting, [
            'key_decisions' => ['مصوبهٔ ۴۱', 'مصوبهٔ ۴۲'],
        ]);

        expect($prompt)->toContain('key_decisions: مصوبهٔ ۴۱ | مصوبهٔ ۴۲');
    });
});

describe("the agent's reconciling rules", function () {
    it('are absent from an ordinary run', function () {
        $agent = new MeetingSummaryAgent(['executive_summary']);

        expect($agent->isMerging())->toBeFalse()
            ->and($agent->instructions())->not->toContain('reconcile the minutes with it');
    });

    it('appear once the run is merging', function () {
        $agent = (new MeetingSummaryAgent(['executive_summary']))
            ->withPrevious(['executive_summary' => 'قدیمی']);

        expect($agent->isMerging())->toBeTrue()
            ->and($agent->instructions())->toContain('reconcile the minutes with it');
    });

    it('tell the model not to echo the draft back', function () {
        // The failure mode of this feature is a model that returns the text it
        // was handed because that is the cheapest answer, and the prompt has to
        // say so explicitly or the feature buys nothing over a fresh run.
        $agent = (new MeetingSummaryAgent(['executive_summary']))
            ->withPrevious(['executive_summary' => 'قدیمی']);

        expect($agent->instructions())
            ->toContain('Do not return the current text unchanged');
    });

    it('do not leak from one run into the next', function () {
        // The SDK mutates the instance it prompts, so a shared instance would
        // let an ordinary run inherit the previous values of the run before it.
        $agent = new MeetingSummaryAgent(['executive_summary']);
        $merged = $agent->withPrevious(['executive_summary' => 'قدیمی']);

        expect($agent->isMerging())->toBeFalse()
            ->and($merged->isMerging())->toBeTrue();
    });

    it('keep the language and structure rules either way', function () {
        $merged = (new MeetingSummaryAgent(['executive_summary']))
            ->withPrevious(['executive_summary' => 'قدیمی']);

        // Merging is a difference of input, not a different job: the rules about
        // language, preamble and what counts as a decision are the same, so
        // they must not be lost when it is switched on.
        foreach ([
            'no preamble',
            'are not decisions',
            'honest gap',
        ] as $rule) {
            expect($merged->instructions())->toContain($rule);
        }
    });
});

describe('what a merging run reconciles against', function () {
    it('reads the value on disk rather than a caller\'s copy', function () {
        // The value that matters is the one a person accepted. A caller holding
        // a stale copy would have the model reconcile against text that was
        // never accepted, and the result would look authoritative.
        $this->meeting->forceFill(['summary' => acceptedSummary([
            'executive_summary' => 'نسخهٔ روی دیسک',
        ])])->save();
        $this->meeting->refresh();

        $sent = null;

        MeetingSummaryAgent::fake(function (string $prompt) use (&$sent): array {
            $sent = $prompt;

            return ['executive_summary' => 'یکپارچه‌شده'];
        });

        $summary = app(BoardMeetingSummarizer::class)->summarize(
            $this->meeting,
            ['executive_summary'],
            null,
            true,
        );

        expect((string) $sent)->toContain('نسخهٔ روی دیسک')
            ->and($summary->values['executive_summary'])->toBe('یکپارچه‌شده');
    });

    it('leaves a field with nothing stored out of the record', function () {
        $this->meeting->forceFill(['summary' => acceptedSummary([
            'executive_summary' => 'خلاصهٔ قبلی',
        ])])->save();
        $this->meeting->refresh();

        $sent = null;

        MeetingSummaryAgent::fake(function (string $prompt) use (&$sent): array {
            $sent = $prompt;

            return ['key_decisions' => ['مصوبهٔ ۴۱']];
        });

        app(BoardMeetingSummarizer::class)->summarize(
            $this->meeting,
            ['key_decisions'],
            null,
            true,
        );

        // Telling the model a person had already accepted a blank is a
        // different claim from "nothing is stored", and the first one would
        // make it preserve the blank.
        expect((string) $sent)->not->toContain('CURRENT RECORD');
    });

    it('is skipped when the caller did not ask for a merge', function () {
        $this->meeting->forceFill(['summary' => acceptedSummary([
            'executive_summary' => 'خلاصهٔ قبلی',
        ])])->save();
        $this->meeting->refresh();

        $sent = null;

        MeetingSummaryAgent::fake(function (string $p) use (&$sent): array {
            $sent = $p;

            return ['executive_summary' => 'تازه'];
        });

        // Through `summarize`, not `promptFor`: the flag lives on the call, so
        // a test that assembles the prompt by hand proves nothing about whether
        // the run honours it.
        app(BoardMeetingSummarizer::class)->summarize(
            $this->meeting,
            ['executive_summary'],
        );

        expect((string) $sent)->not->toContain('CURRENT RECORD')
            ->and((string) $sent)->not->toContain('خلاصهٔ قبلی');
    });

    it('has nothing to reconcile against on a first run', function () {
        // A meeting with no summary must not be told it has an accepted draft
        // that is empty; the run then behaves exactly like a fresh one.
        $prompt = $this->summarizer->promptFor($this->meeting, []);

        expect($prompt)->not->toContain('CURRENT RECORD');
    });
});

describe('asking for a merging run', function () {
    it('is off unless it was asked for', function () {
        expect((new GenerateBoardMeetingSummaryJob(1))->merge)->toBeFalse();
    });

    it('is part of the request, not of the field list', function () {
        // "Write this field fresh" and "improve this field" are two different
        // requests. If the lock treated them as one, asking twice on purpose —
        // once to compare — would silently return the first answer.
        $fresh = new GenerateBoardMeetingSummaryJob(1, ['topics']);
        $merge = new GenerateBoardMeetingSummaryJob(
            1,
            ['topics'],
            null,
            SummaryWriteMode::Review,
            true,
        );

        expect($fresh->uniqueId())->not->toBe($merge->uniqueId());
    });

    it('reaches the job through the endpoint', function () {
        Queue::fake();
        $meeting = BoardMeeting::factory()->create(['minutes' => 'صورتجلسه.']);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", [
                'fields' => ['key_decisions'],
                'merge' => true,
            ])
            ->assertOk();

        Queue::assertPushed(
            GenerateBoardMeetingSummaryJob::class,
            fn (GenerateBoardMeetingSummaryJob $job): bool => $job->merge === true
                && $job->fields === ['key_decisions'],
        );
    });

    it('rejects a merge flag that is not a boolean', function () {
        $meeting = BoardMeeting::factory()->create(['minutes' => 'صورتجلسه.']);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->postJson("/api/board-meetings/{$meeting->id}/summary", ['merge' => 'yes please'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('merge');
    });
});
