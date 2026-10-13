<?php

namespace App\Domains\Document\Events;

use App\Events\BaseAuditEvent;

/**
 * One audit record per downloaded bundle (aggregate of the single-file
 * download events, which stay on the per-document endpoints): the actor
 * pulled the same documents out of the system in one archive.
 */
class DocumentsZipped extends BaseAuditEvent
{
    /**
     * @param  list<int>  $usageIds
     */
    public function __construct(
        private readonly int $count,
        private readonly array $usageIds,
    ) {}

    public function eventName(): string
    {
        return 'document.zipped';
    }

    public function category(): string
    {
        return 'document';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'document_zip',
            'count' => $this->count,
        ];
    }

    public function metadata(): ?array
    {
        return [
            'usage_ids' => $this->usageIds,
        ];
    }

    public function description(): ?string
    {
        return "{$this->count} documents downloaded as one zip bundle";
    }
}
