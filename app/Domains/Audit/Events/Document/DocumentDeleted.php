<?php

namespace App\Domains\Audit\Events\Document;

use App\Domains\Audit\Events\BaseAuditEvent;

class DocumentDeleted extends BaseAuditEvent
{
    public function __construct(
        private readonly int $usageId,
        private readonly string $entityType,
        private readonly int|string $entityId,
    ) {}

    public function eventName(): string
    {
        return 'document.deleted';
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
        return "Document usage {$this->usageId} trashed from {$this->entityType} {$this->entityId}";
    }

    public function metadata(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
        ];
    }
}
