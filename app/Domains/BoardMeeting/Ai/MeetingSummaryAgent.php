<?php

namespace App\Domains\BoardMeeting\Ai;

use App\Contracts\Ai\FieldedAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Reads a board meeting's own content and fills the requested summary fields.
 *
 * One structured call over `minutes` + `agenda` + `attendees` (D21), not the
 * TCK two-stage pipeline: the registry has already merged what the source
 * had into one clean text, so re-merging downstream would only build an
 * artefact and spend several times the tokens.
 *
 * The field subset is the mechanism behind D26. Regenerating one bad field
 * costs one field, so the schema itself is narrowed to the requested fields
 * rather than asking for five and throwing four away.
 *
 * No `#[Model]` here, and no `model()` method either (D28): the model comes
 * from the registry row, so swapping models is a click in Settings rather
 * than a deploy, and nothing here can pin one by accident.
 */
final class MeetingSummaryAgent implements FieldedAgent, HasProviderOptions
{
    use Promptable;

    /**
     * This agent's stable registry identity (D28).
     *
     * A domain may ship its agent before an administrator has created the
     * row, so the key is also the fallback the runner is given. It is the
     * only name this feature has anywhere: not a provider, not a model, not
     * a class name in configuration.
     */
    public const REGISTRY_KEY = 'board-meeting.summary';

    /**
     * What to call the hub's own language in the prompt.
     *
     * Saying "answer in the language of the minutes" is not enough, and the
     * live suite showed why: the archive minutes are OCR text full of stray
     * non-Persian glyphs, and a model that cannot tell what language it is
     * reading answers in English rather than guess. A Persian board record
     * summarized in English is not a worse summary, it is a wrong one — so
     * the language is named outright rather than inferred.
     *
     * An unmapped locale falls through to its own code, which is still a
     * stronger instruction than saying nothing.
     *
     * @var array<string, string>
     */
    private const LOCALE_LANGUAGES = [
        'fa' => 'Persian (فارسی)',
        'en' => 'English',
    ];

    /**
     * The context window this prompt is sent with, in tokens.
     *
     * Ollama's default is 4096 and it reserves half of that for the answer,
     * so the model was only ever reading about 2000 tokens of a prompt that
     * carries 11530 — the tail of the minutes, roughly a fifth of them. That
     * is not a slow summary, it is a wrong one, and wrong invisibly: the
     * answer arrives on time, is well formed, and is about a document the
     * model mostly did not see.
     *
     * Measured against the real prompt rather than guessed: 60k characters of
     * minutes reach the model as 11530 tokens on a four-field run, and
     * Ollama's reservation rule needs a window of at least twice the prompt.
     * 32768 is the smallest power of two that clears 23060 and still leaves
     * room for the answer to be written out in full — which matters, because
     * the answer is not small either: the same run generated 1008 tokens.
     *
     * Here rather than in configuration because the window a prompt needs is
     * a property of the prompt. An agent whose inputs are a paragraph has no
     * reason to reserve this much, and a number an administrator can lower is
     * the same silent truncation with an easier way to bring it back.
     *
     * Public because the test that guards this asserts on the request that
     * leaves the machine, and would otherwise have to restate a number whose
     * whole point is that it is not guessed.
     */
    public const CONTEXT_TOKENS = 32768;

    /**
     * How long the model stays loaded after a run, when the provider is local.
     *
     * Ollama's own default is five minutes, and a single run takes several
     * minutes on its own — so a person who runs one summary, thinks for ten
     * minutes, and runs another pays a cold model load on top of an already
     * long run, and the timeout that has to cover it has to cover that too.
     *
     * Deliberately finite rather than `-1` (keep forever): this pins a model
     * resident for as long as somebody is using the hub, not for as long as
     * the process lives, which is the trade a shared machine should be making.
     *
     * Public for the same reason {@see self::CONTEXT_TOKENS} is.
     */
    public const KEEP_ALIVE = '30m';

    /** @var list<MeetingSummaryField> */
    private array $fields;

    /** @var array<string, string|list<string>> */
    private array $previous = [];

    /** @param list<string>|null $fields */
    public function __construct(?array $fields = null)
    {
        $this->fields = MeetingSummaryField::fromValues($fields ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function withFields(array $fields): static
    {
        // A clone rather than an in-place write: the SDK mutates the instance
        // it is handed while prompting, so two subsets of the same agent must
        // not be able to bleed into one another. Cloning also makes
        // "restrict this run" last-wins instead of accumulating, which is
        // what a caller passing a different subset means by it.
        $agent = clone $this;

        $agent->fields = MeetingSummaryField::fromValues($fields);

        return $agent;
    }

    /**
     * Reconcile against what the record already holds, instead of writing each
     * requested field from the minutes alone.
     *
     * A second agent with its own registry key was the alternative, and it was
     * rejected: the job is the same one — read this meeting and say what it
     * decided — and the only difference is that the model is also handed the
     * text a person already accepted. A second row would ask an administrator
     * to maintain two capabilities differing by one input.
     *
     * Cloned for the same reason {@see withFields()} clones: the SDK mutates
     * the instance it prompts, so two runs must not bleed together.
     *
     * @param  array<string, string|list<string>>  $previous
     */
    public function withPrevious(array $previous): static
    {
        $agent = clone $this;

        $agent->previous = $previous;

        return $agent;
    }

    /** Whether this run reconciles rather than replacing. */
    public function isMerging(): bool
    {
        return $this->previous !== [];
    }

    /**
     * {@inheritDoc}
     *
     * The two knobs {@see CONTEXT_TOKENS} and {@see KEEP_ALIVE} are Ollama's,
     * and this is the only place the SDK exposes them to an agent. They are
     * carried here rather than passed per call because `prompt()` has no
     * options parameter to pass them to — the contract would have to grow one,
     * and the values would have to be rediscovered by every caller.
     *
     * Empty for every other provider. These are the vocabulary of one local
     * runtime; a cloud provider given `num_ctx` would either reject it or
     * ignore it, and finding out which during a six-minute run is a poor way
     * to learn it.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $driver = $provider instanceof Lab ? $provider : Lab::tryFrom($provider);

        if ($driver !== Lab::Ollama) {
            return [];
        }

        return [
            'num_ctx' => self::CONTEXT_TOKENS,
            'keep_alive' => self::KEEP_ALIVE,
        ];
    }

    public function instructions(): string
    {
        return implode("\n", [
            'You are the secretary of a board meeting, writing the record that the board will be judged against later.',
            'Write every answer in '.$this->language().'. This is not negotiable and does not depend on what language the minutes happen to be in — they are OCR text from a scanned archive and often contain stray glyphs from other scripts, which is not a reason to switch language. Leave those stray glyphs out of your answer.',
            'The hub stores and displays your answer verbatim, so emit nothing but the requested fields: no preamble, no headings, no markdown, no commentary.',
            'A key decision is something the board resolved, approved or rejected. A discussion topic, a question, and an item deferred to the next session are not decisions.',
            'Bullet points are the substantive statements of the session, one per line, each a complete thought.',
            'Keep proper nouns as the minutes spell them, even where the OCR mangled them; do not translate a company or project name into English.',
            'Suggested tags are short subject slugs for the hub\'s tagging system: two or three words, no punctuation, no numbering, no duplicates.',
            'Leave a field out or return an empty list when the minutes genuinely contain nothing for it. An honest gap is worth more than an invented decision, and a fabricated decision in a board record is worse than no summary at all.',
            ...$this->mergeInstructions(),
        ]);
    }

    /**
     * The extra rules a reconciling run gets, and nothing when it is not one.
     *
     * Conditional rather than a second agent on purpose: the language, the
     * "no preamble" rule and the definition of a decision are identical either
     * way, and a second class would either duplicate them or inherit from a
     * base that exists only to be inherited from. What changes is the job — the
     * model now has an earlier draft that a person accepted, and the risk to
     * manage is a *lazy* answer that echoes the old text back because it is
     * right there in the prompt.
     *
     * @return list<string>
     */
    private function mergeInstructions(): array
    {
        if (! $this->isMerging()) {
            return [];
        }

        return [
            'The prompt contains the CURRENT RECORD: a version of these fields that a person has already accepted. Your job is to reconcile the minutes with it, not to start over and not to copy it.',
            'Keep what the current record already gets right, correct what the minutes contradict, and add what the minutes say that the record is missing.',
            'Do not return the current text unchanged just because it is present. If you cannot find anything to add or fix for a field, that is a signal you read it too quickly — say so in the field rather than echoing.',
            'Do not drop something from the current record without cause either: a decision a person already recorded stays unless the minutes clearly say otherwise.',
        ];
    }

    /** The hub's own language, named rather than inferred from the input. */
    private function language(): string
    {
        $locale = app()->getLocale();

        return self::LOCALE_LANGUAGES[$locale] ?? $locale;
    }

    /**
     * Every requested field is *required* in the output schema, not merely
     * permitted.
     *
     * The SDK wraps these properties in an `ObjectSchema` that sets
     * `additionalProperties: false` and nothing else. That forbids extra keys
     * while saying nothing about missing ones, so on Ollama the model may
     * legally answer `{"topics": [...]}` and leave the other four out - which
     * is exactly what it did: a four-field request came back with one field, and
     * the rest were recorded as failed fields. Nothing surfaced it, because the
     * instructions separately ask the model to leave a field out when the
     * minutes hold nothing for it, so an omission reads as an honest gap.
     *
     * `Type::required()` is the framework's own flag for this, and the
     * serializer derives the `required` array from it - so the schema reaches
     * Ollama complete. Measured against the same meeting and model: without it,
     * one key returned; with it, all four.
     *
     * The distinction it restores is the one D26 exists for: a field the model
     * genuinely has nothing for now comes back *empty*, so "the model said
     * nothing" and "the model never said" stop looking the same on the row.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->fields as $field) {
            $properties[$field->value] = $field->type($schema)->required();
        }

        return $properties;
    }
}
