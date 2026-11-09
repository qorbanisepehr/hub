<?php

namespace App\Domains\Tagging\Value;

use App\Domains\Tagging\Enums\ConsolidationStrategy;
use App\Domains\Tagging\Models\Tag;

/**
 * What the person asked the consolidator to look at, and how boldly.
 *
 * Both halves travel into the prompt (`term`) and the run's findings
 * (`context`) on purpose: the proposal is not stored, so the client that
 * re-reads it has to say what it asked for a second time — and a person
 * looking at the result must be able to see that a proposal only covers the
 * word they typed, not the whole registry.
 *
 * A focused run is how a large registry stays reviewable: the model's answer is
 * only as good as what it can see, and "the project X family" is a question a
 * person can hold in their head in a way that a list of forty cross-cutting
 * groups is not.
 */
final readonly class ConsolidationFocus
{
    public function __construct(
        public ConsolidationStrategy $strategy,
        public ?string $term = null,
    ) {}

    /**
     * The tags a focused run may consider, out of the whole inventory.
     *
     * Matching is deliberately loose — substring against the label or the
     * slug, in either direction of the word — because the point is to catch
     * the family around the word (`سونگون` finding `پروژه سونگون`), and a
     * strict match would only ever see the tag itself. The term is compared
     * against the name as written and against a normalised copy, so
     * half-space and Arabic letter forms do not hide a match.
     *
     * @param  list<Tag>  $inventory
     * @return list<Tag>
     */
    public function narrow(array $inventory): array
    {
        if ($this->term === null) {
            return $inventory;
        }

        $needle = self::fold($this->term);

        if ($needle === '') {
            return $inventory;
        }

        return array_values(array_filter(
            $inventory,
            static fn (Tag $tag): bool => str_contains(self::fold($tag->name), $needle)
                || str_contains(self::fold($tag->slug), $needle)
                || str_contains($needle, self::fold($tag->name)),
        ));
    }

    /**
     * The term as lowercase text with Persian letter variants and zero-width
     * joiners flattened — the same word typed two ways should match itself.
     */
    private static function fold(string $value): string
    {
        return str_replace(
            ['ي', 'ك', 'ة', 'ۀ', '‌', ' '],
            ['ی', 'ک', 'ه', 'ه', '', ''],
            mb_strtolower(trim($value)),
        );
    }
}
