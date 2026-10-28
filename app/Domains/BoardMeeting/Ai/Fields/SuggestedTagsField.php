<?php

namespace App\Domains\BoardMeeting\Ai\Fields;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The subjects of the meeting, as tags the hub's own registry can hold.
 *
 * The only field that carries structure. A tag is two strings because the hub
 * keeps two: `name` is the Persian label a person reads, `slug` is the stable
 * key the pivot, the filters and the edit dialog already use. A model asked
 * for a single string can only ever fill one of the two, which is exactly how
 * a hub with Persian tag names ended up being shown English tag slugs.
 *
 * The rules are conditional on the run actually asking for tags: they name a
 * KNOWN TAGS section that the prompt carries only when tags were requested, so
 * a run that was not asked for tags should not be told to reuse a list it will
 * never see.
 */
final class SuggestedTagsField implements SummaryFieldSpec
{
    public function name(): string
    {
        return 'suggested_tags';
    }

    public function isList(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): Type
    {
        return $schema->array()->items(
            $schema->object([
                'name' => $schema->string()->required()
                    ->description('the tag label, written in Persian'),
                'slug' => $schema->string()->required()
                    ->description('a short lowercase latin transliteration, letters/digits/hyphens only'),
            ]),
        );
    }

    /**
     * @return list<string>
     */
    public function instructions(SummaryFieldContext $context): array
    {
        if (! in_array(MeetingSummaryField::SuggestedTags, $context->fields, true)) {
            return [];
        }

        return [
            'Suggested tags are the subject labels of the hub\'s tagging system. Each tag is two strings: `name` is the label written in '.$context->language.', and `slug` is a short lowercase latin transliteration of it using letters, digits and hyphens only (for example "miduk-phase-3-project"). No punctuation, no numbering, no duplicates.',
            'The prompt lists the tags the hub already has under KNOWN TAGS. When one of those already means what you mean, reuse it and copy its name exactly as written — a near-duplicate is worse than no new tag. Invent a tag only when nothing on that list fits.',
        ];
    }

    /**
     * A proposal whose name or slug is missing is completed from the other
     * rather than dropped, because either half alone is still a usable tag and
     * an incomplete pair is a model slip, not a reason to lose the tag. The
     * list is de-duplicated by slug — the tag's own identity — so the same tag
     * named twice does not become two rows of evidence.
     *
     * @return list<array{name: string, slug: string}>
     */
    public function read(mixed $raw): string|null|array
    {
        if (! is_array($raw)) {
            return [];
        }

        $proposals = [];

        foreach ($raw as $item) {
            // A stored list from before tags carried a name: the string is the
            // slug, and its label was the slug. Readable rather than migrated,
            // so an old row keeps the tags it had.
            if (is_string($item)) {
                $slug = trim($item);

                if ($slug !== '') {
                    $proposals[$slug] = ['name' => $slug, 'slug' => $slug];
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $name = is_string($item['name'] ?? null) ? trim($item['name']) : '';
            $slug = is_string($item['slug'] ?? null) ? trim($item['slug']) : '';

            if ($name === '' && $slug === '') {
                continue;
            }

            $name = $name !== '' ? $name : $slug;
            $slug = $slug !== '' ? $slug : $name;

            $proposals[$slug] = ['name' => $name, 'slug' => $slug];
        }

        return array_values($proposals);
    }
}
