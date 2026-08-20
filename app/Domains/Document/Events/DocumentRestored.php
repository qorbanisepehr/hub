<?php

namespace App\Domains\Document\Events;

use App\Events\BaseAuditEvent;

class DocumentRestored extends BaseAuditEvent
{
    public function __construct(
        private readonly int $usageId,
        private readonly string $entityType,
        private readonly int|string $entityId,
    ) {}

    public function eventName(): string
    {
        return 'document.restored';
    }

    public function category(): string
    {
        return 'document';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'document_usage',
            'id' => $this->usageId,
        ];
    }

    public function description(): ?string
    {
        return "Document usage {$this->usageId} restored to {$this->entityType} {$this->entityId}";
    }

    public function metadata(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
        ];
    }
}
