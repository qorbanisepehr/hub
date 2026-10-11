<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Value\AiRun;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Tagging\Services\TagService;
use Illuminate\Support\Str;

/**
 * Produces a board meeting's summary from the meeting's own content.
 *
 * The domain owns the prompt because the domain owns the vocabulary. What
 * counts as a decision, what a topic is, and which tags the hub's tagging
 * vocabulary can actually hold are all BoardMeeting's questions, and none of
 * them can be answered inside the Ai domain without that domain learning what
 * a board meeting is. So this service names no provider and no model: it hands
 * a key and a prompt to {@see AiAgentRunner} and reads an artifact back, and
 * everything about *which* model ran is decided in Settings (D28). The optional
 * provider/model pair is the one thing it does name, and only because a person
 * picked it for one run in the UI — the same exception the runner's own
 * docblock makes. The pair is passed through unexamined: whether it could still
 * be served was answered by the request before the run was queued, and a name
 * that goes stale in the queue degrades to the agent's binding instead of
 * failing the job.
 *
 * The prompt is assembled from `minutes` + `agenda` + `attendees` only (D21).
 * Registry columns such as `origin` and `provenance` describe where the row
 * came from, not what the board did, and feeding them to a model only invites
 * it to summarize the import rather than the meeting. A run that asks for
 * `suggested_tags` also gets the hub's own tag names appended, because a model
 * cannot reuse a vocabulary it was never shown — see {@see knownTagsSection()}.
 */
class BoardMeetingSummarizer
{
    /**
     * How long one model call may take, in seconds.
     *
     * Measured, not guessed - and it has grown twice, because each time the
     * earlier figure turned out to be about a smaller job than the one that
     * actually runs. A real board meeting's prompt is roughly 60,000
     * characters, which tokenises to 11,530 tokens; a complete run against a
     * local Ollama on a 12B model measured 550s warm, plus 94s of model
     * loading when the model had been unloaded since the last run - about
     * 645s end to end. The 160s this quoted first, and the 180s after that,
     * were both measured while the model was reading a fifth of that prompt,
     * because Ollama's default context window was truncating it, so neither
     * was ever a figure about this work at all. The 405s in between was
     * measured on a run whose answer happened to be 52 tokens instead of
     * 1008, which is most of the difference: generation is the one part of
     * this that does not scale with the minutes.
     *
     * The SDK's own default is 60s, so without this every real summary dies
     * at exactly one minute with `cURL error 28 ... 0 bytes received` - a
     * timeout that looks like a broken provider and is not one at all.
     *
     * 900 rather than the 645 the measurement showed, because the number it
     * has to survive is the *worst* cold run on a machine that is also doing
     * other things - the first attempt at this timed out at 600 while the
     * test suite and a frontend build were competing for the same CPU. A
     * generous timeout costs nothing when a run succeeds and everything when
     * a run that would have finished is killed: the retry pays the whole
     * cost again from the beginning.
     *
     * Deliberately a constant rather than configuration. Nobody has asked to
     * tune this per environment, and a setting an admin can change is a setting
     * that can be set too low - which reintroduces the same silent failure in a
     * place where it is much harder to notice.
     *
     * Three numbers must stay above it, and all three fail in ways that look
     * like something else: the job's own `timeout` (1080) or the worker kills
     * a call that was still allowed to finish; the queue's `retry_after`
     * (1500) or the queue hands the job to a second worker while the first is
     * still running it, paying for the same run twice; and the job's
     * `uniqueFor` (1200), which sits between the two - long enough that a run
     * still going does not lose its lock, short enough that a lock left behind
     * by a worker that died is gone before `retry_after` tries to re-dispatch
     * the job, or that retry would be dropped as a duplicate and the run would
     * never happen at all. The ordering is this constant < job `timeout` <
     * `uniqueFor` < `retry_after`, and it is why none of them can be raised
     * alone.
     */
    private const TIMEOUT_SECONDS = 900;

    /**
     * How many characters of the hub's tag vocabulary a tag run may carry.
     *
     * The whole vocabulary is small today — 338 tags, 5,626 characters of
     * names, about 1,100 tokens at the measured ratio — so this ceiling does
     * not bite yet. It exists so that a hub that grows to thousands of tags
     * degrades by *truncating the list* rather than by silently overflowing the
     * context window and losing the minutes instead. The list is written
     * most-used first by {@see TagService::vocabulary()}, so the cut, when it
     * comes, drops the tags a meeting is least likely to be about.
     */
    private const VOCABULARY_CHAR_BUDGET = 8000;

    public function __construct(
        private readonly AiAgentRunner $runner,
        private readonly TagService $tags,
    ) {}

    /**
     * @param  list<string>  $fields  the fields to fill; empty means all of
     *                                them, which is what a first run and the
     *                                bulk action both send
     * @param  string|null  $provider  a gateway chosen for this one run from the
     *                                 UI. Optional because naming it is not
     *                                 necessary: an unqualified model picks the
     *                                 gateway the rest of the ladder would have
     *                                 used, which is the ordinary case
     * @param  string|null  $model  a model chosen for this one run from the UI,
     *                              read on whichever provider the run resolves
     *                              to. Names rather than ids because a name is
     *                              unique only within a provider, and the id this
     *                              used to take also decided the provider — so
     *                              the two could never be disagreed with each
     *                              other and the choice could not be expressed
     *                              as "this model, on that gateway"
     *
     * @throws AiNotConfigured when no provider can answer
     * @throws \ValueError when a field name is outside the vocabulary
     */
    public function summarize(
        BoardMeeting $meeting,
        array $fields = [],
        ?string $provider = null,
        ?string $model = null,
        bool $merge = false,
    ): MeetingSummary {
        $requested = MeetingSummaryField::fromValues($fields);

        // Whether to hand the model the hub's tag vocabulary: only a run that
        // is actually asked for tags can use it, and giving it to the others
        // only spends context on a list they were told to ignore.
        $withTags = in_array(MeetingSummaryField::SuggestedTags, $requested, true);

        // What the record already holds, narrowed to this run's fields. Read
        // from the row rather than passed in, because the value that matters is
        // the one on disk: a caller holding a stale copy would have the model
        // reconcile against a text nobody ever accepted.
        $previous = $merge
            ? $this->currentValues($meeting, $requested)
            : [];

        $run = $this->runner->run(
            key: MeetingSummaryAgent::REGISTRY_KEY,
            prompt: $this->promptFor($meeting, $previous, $withTags),
            agentClass: MeetingSummaryAgent::class,
            provider: $provider,
            model: $model,
            settings: [
                'fields' => array_map(
                    fn (MeetingSummaryField $field): string => $field->value,
                    $requested,
                ),
                'previous' => $previous,
                'timeout' => self::TIMEOUT_SECONDS,
            ],
        );

        return MeetingSummary::fromRun($this->resolveTags($run, $requested), $requested);
    }

    /**
     * The stored value of each requested field, for a reconciling run.
     *
     * Only fields the record actually holds. A field with no stored value has
     * nothing to reconcile against, and passing an empty one would tell the
     * model that a person had already accepted a blank.
     *
     * @param  list<MeetingSummaryField>  $requested
     * @return array<string, string|list<string>|list<array{name: string, slug: string}>>
     */
    private function currentValues(BoardMeeting $meeting, array $requested): array
    {
        $stored = MeetingSummary::fromArray($meeting->summary);
        $values = [];

        foreach ($requested as $field) {
            $value = $stored?->values[$field->value] ?? null;

            if ($value === null || $value === []) {
                continue;
            }

            $values[$field->value] = $value;
        }

        return $values;
    }

    /**
     * The meeting as one block of text, sectioned by what each part is for.
     *
     * Public so a caller that needs a different model for one run — the live
     * suite's reason — can build the same prompt without reimplementing what
     * belongs to this domain.
     *
     * Labels are in English and the content is not: the prompt is written for
     * a model, and mixing a translated label into the Persian minutes would
     * risk the answer following the label's language instead of the meeting's.
     *
     * `$withTags` appends the hub's own tag names, and is set by the caller
     * from the request rather than derived here, so the prompt and the agent's
     * instructions agree on whether tags are in play.
     *
     * @param  array<string, mixed>  $current
     */
    public function promptFor(BoardMeeting $meeting, array $current = [], bool $withTags = false): string
    {
        $sections = [
            "Registry key: {$meeting->held_on_jalali}, session {$meeting->sequence} of that day.",
            $this->currentRecordSection($current),
            $this->section('Title', $meeting->title),
            $this->section('Agenda', $this->agendaLines($meeting)),
            $this->section('Attendees', $this->attendeeLines($meeting)),
            $withTags ? $this->knownTagsSection() : null,
            $this->section('Minutes', $meeting->minutes),
        ];

        return implode("\n\n", array_filter($sections, fn (?string $s): bool => $s !== null && $s !== ''));
    }

    /**
     * The hub's existing tags, as the list a tagging run must reuse from.
     *
     * Without this the model can only answer with tags it invents, because it
     * has never seen the hub's — and the hub's are the ones a person will
     * filter by. The list is names, not slugs: the name is what the model is
     * asked to echo, and {@see TagService::canonicalize()} maps it back to the
     * real slug. Truncated at {@see VOCABULARY_CHAR_BUDGET} characters.
     */
    private function knownTagsSection(): ?string
    {
        $included = [];
        $length = 0;

        foreach ($this->tags->vocabulary() as $name) {
            $cost = mb_strlen($name) + 1;

            if ($length + $cost > self::VOCABULARY_CHAR_BUDGET) {
                break;
            }

            $included[] = $name;
            $length += $cost;
        }

        if ($included === []) {
            return null;
        }

        return "KNOWN TAGS (reuse one of these when it means what you mean; do not invent a near-duplicate):\n"
            .implode(' | ', $included);
    }

    /**
     * Snap a run's tag proposals onto the hub's vocabulary before they are
     * stored.
     *
     * The model is asked to reuse known tags, but it cannot be trusted to
     * spell, and a proposal it got wrong would become a near-duplicate tag
     * nobody meant to create. The resolver is the deterministic half of that
     * ask: it matches by name or slug and substitutes the hub's own pair, so
     * reuse holds whether or not the model did. Only the tags field is touched;
     * the other four are copied through untouched.
     *
     * @param  list<MeetingSummaryField>  $requested
     */
    private function resolveTags(AiRun $run, array $requested): AiRun
    {
        $name = MeetingSummaryField::SuggestedTags->value;

        if (! in_array(MeetingSummaryField::SuggestedTags, $requested, true)
            || ! is_array($run->structured[$name] ?? null)) {
            return $run;
        }

        $proposals = $run->structured[$name];

        return new AiRun(
            text: $run->text,
            structured: [
                ...$run->structured,
                $name => $this->tags->canonicalize(array_values($proposals)),
            ],
            provider: $run->provider,
            model: $run->model,
            usage: $run->usage,
            providerKey: $run->providerKey,
        );
    }

    /**
     * The stored summary, as a section a reconciling run can read.
     *
     * Null for an ordinary run, so the prompt is byte-for-byte what it was
     * before this feature existed — a run that is not reconciling must not even
     * hint that an earlier draft exists, or the model starts treating the
     * stored text as something to preserve.
     *
     * @param  array<string, mixed>  $current
     */
    private function currentRecordSection(array $current): ?string
    {
        if ($current === []) {
            return null;
        }

        $lines = ['CURRENT RECORD (accepted by a person; reconcile with it, do not copy it):'];

        foreach ($current as $field => $value) {
            $lines[] = $field.': '.$this->renderCurrentValue($value);
        }

        return implode("\n", $lines);
    }

    /**
     * One stored field value as a line the model can read.
     *
     * A list of strings joins with `|`. A list of tag proposals — the one
     * field whose items are pairs — renders each as `name (slug)`, so the model
     * sees both what the tag is called and the key it holds, rather than the
     * word "Array" repeated once per tag.
     */
    private function renderCurrentValue(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        return implode(' | ', array_map(
            fn (mixed $item): string => is_array($item)
                ? trim(($item['name'] ?? '').' ('.($item['slug'] ?? '').')')
                : (string) $item,
            $value,
        ));
    }

    private function agendaLines(BoardMeeting $meeting): ?string
    {
        if ($meeting->agenda === []) {
            return null;
        }

        return collect($meeting->agenda)
            ->map(fn (AgendaItem $item): string => Str::padLeft((string) $item->position, 2, '0').'. '.$item->title)
            ->implode("\n");
    }

    /**
     * Presence is part of the record, so an absent member is marked rather
     * than dropped: who was in the room changes what a decision meant.
     */
    private function attendeeLines(BoardMeeting $meeting): ?string
    {
        if ($meeting->attendees === []) {
            return null;
        }

        return collect($meeting->attendees)
            ->map(fn (Attendee $attendee): string => sprintf(
                '%s (%s%s)',
                $attendee->name,
                $attendee->role->value,
                $attendee->isPresent ? '' : ', absent',
            ))
            ->implode("\n");
    }

    private function section(string $label, ?string $body): ?string
    {
        return $body === null || trim($body) === '' ? null : "{$label}:\n{$body}";
    }
}
