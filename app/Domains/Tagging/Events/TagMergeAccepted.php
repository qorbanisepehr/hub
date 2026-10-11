<?php

namespace App\Domains\Tagging\Events;

use App\Events\BaseAuditEvent;

/**
 * A person accepted a consolidation proposal and the hub folded tags together.
 *
 * Its own event rather than `TagMergeProposed` (which is not audited at all —
 * asking a model a question is not an act on the hub's data) because the actor,
 * the moment and the meaning are different: this is content changing in the
 * vocabulary at a person's hand, and it records a diff the way the other
 * content-changing events do.
 *
 * The groups are passed in rather than read back from the tags, because by the
 * time this fires the duplicate rows are gone — an audit row that could only
 * describe the vocabulary as it now stands would not be able to say what was
 * merged into what.
 */
class TagMergeAccepted extends BaseAuditEvent
{
    /**
     * @param  list<array{canonical: string, duplicates: list<string>}>  $groups
     */
    public function __construct(
        private readonly array $groups,
        private readonly int $attachmentsMoved,
    ) {}

    public function eventName(): string
    {
        return 'tagging.merged';
    }

    public function category(): string
    {
        return 'tagging';
    }

    public function description(): ?string
    {
        return sprintf(
            'Tags merged: %d removed across %d group(s), %d attachment(s) moved',
            $this->removedCount(),
            count($this->groups),
            $this->attachmentsMoved,
        );
    }

    /**
     * @return array{old: array<string, mixed>, new: array<string, mixed>}|null
     */
    public function changes(): ?array
    {
        return [
            'old' => ['tags' => $this->allSlugs()],
            'new' => ['tags' => array_column($this->groups, 'canonical')],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array
    {
        return [
            'groups' => $this->groups,
            'attachments_moved' => $this->attachmentsMoved,
        ];
    }

    private function removedCount(): int
    {
        return array_sum(array_map(
            static fn (array $group): int => count($group['duplicates']),
            $this->groups,
        ));
    }

    /**
     * Every slug the merge touched, canonical and duplicate alike.
     *
     * @return list<string>
     */
    private function allSlugs(): array
    {
        $slugs = [];

        foreach ($this->groups as $group) {
            $slugs[] = $group['canonical'];

            foreach ($group['duplicates'] as $duplicate) {
                $slugs[] = $duplicate;
            }
        }

        return $slugs;
    }
}
