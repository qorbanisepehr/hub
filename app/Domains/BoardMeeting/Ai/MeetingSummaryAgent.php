<?php

namespace App\Domains\BoardMeeting\Ai;

use App\Contracts\Ai\FieldedAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
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
final class MeetingSummaryAgent implements FieldedAgent
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

    /** @var list<MeetingSummaryField> */
    private array $fields;

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
        ]);
    }

    /** The hub's own language, named rather than inferred from the input. */
    private function language(): string
    {
        $locale = app()->getLocale();

        return self::LOCALE_LANGUAGES[$locale] ?? $locale;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->fields as $field) {
            $properties[$field->value] = $field->type($schema);
        }

        return $properties;
    }
}
