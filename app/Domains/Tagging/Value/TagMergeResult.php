<?php

namespace App\Domains\Tagging\Value;

/**
 * What a merge actually did, after re-validating the accepted groups against
 * the live vocabulary.
 *
 * `groups` is the *surviving* selection: a group whose canonical tag was
 * deleted between propose and accept is absent here, not silently merged into
 * something else. It is slugs rather than models because by the time this is
 * read the duplicate rows are gone and the canonical has changed hands.
 */
final readonly class TagMergeResult
{
    /**
     * @param  list<array{canonical: string, duplicates: list<string>}>  $groups
     * @param  int  $attachmentsMoved  duplicate attachment rows folded onto
     *                                 their canonical tag. Two duplicate tags
     *                                 attached to the same meeting count twice
     *                                 here and once on the canonical, which is
     *                                 the point of the number: it measures the
     *                                 work, not the resulting attachment count.
     */
    public function __construct(
        public array $groups,
        public int $attachmentsMoved,
    ) {}

    /** How many tags the merge removed (the canonical tags stay). */
    public function removedCount(): int
    {
        return array_sum(array_map(
            static fn (array $group): int => count($group['duplicates']),
            $this->groups,
        ));
    }
}
