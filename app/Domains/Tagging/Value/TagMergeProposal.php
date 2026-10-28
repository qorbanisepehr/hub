<?php

namespace App\Domains\Tagging\Value;

/**
 * What one consolidation run proposed, in the Tagging domain's own vocabulary.
 *
 * Provider and model travel with the groups for the same reason they travel
 * with a summary (§6.3): "which model proposed this merge?" has to stay
 * answerable while a person is looking at it. The proposal itself is not stored
 * anywhere — it is a pure function of the vocabulary, handed to the client and
 * re-validated when it comes back.
 */
final readonly class TagMergeProposal
{
    /**
     * @param  list<TagMergeGroup>  $groups
     */
    public function __construct(
        public array $groups,
        public string $provider,
        public ?string $model,
    ) {}

    /** Whether the model found nothing worth merging. */
    public function isEmpty(): bool
    {
        return $this->groups === [];
    }
}
