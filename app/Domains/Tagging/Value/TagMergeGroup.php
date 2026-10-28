<?php

namespace App\Domains\Tagging\Value;

use App\Domains\Tagging\Models\Tag;

/**
 * One subject the consolidator proposes to collapse into a single tag.
 *
 * The canonical tag is a model rather than a slug because the proposal is read
 * by a person who has to recognise it — name, slug and usage count all matter
 * to "keep this one, not that one" — and carrying the row through means the
 * resource and the merge both work from the same loaded tag rather than
 * re-querying it.
 *
 * `reason` is the model's own words and is never trusted as data; it is shown
 * beside the group so a person can judge the merge, and dropped on accept.
 */
final readonly class TagMergeGroup
{
    /**
     * @param  list<Tag>  $duplicates
     */
    public function __construct(
        public Tag $canonical,
        public array $duplicates,
        public string $reason,
    ) {}

    /** How many tags this group would remove if accepted. */
    public function removedCount(): int
    {
        return count($this->duplicates);
    }
}
