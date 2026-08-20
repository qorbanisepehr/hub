<?php

namespace App\Domains\Document\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class DocumentUploaded extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $document,
        private readonly Model $entity,
        private readonly string $categoryName,
    ) {}

    public function eventName(): string
    {
        return 'document.uploaded';
    }

    public function category(): string
    {
        return 'document';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'document',
            'id' => $this->document->getKey(),
        ];
    }

    public function description(): ?string
    {
        return "Document {$this->document->getKey()} uploaded to ".get_class($this->entity)." {$this->entity->getKey()}";
    }

    public function metadata(): array
    {
        return [
            'entity_type' => get_class($this->entity),
            'entity_id' => $this->entity->getKey(),
            'category' => $this->categoryName,
            'mime_type' => $this->document->mime_type ?? null,
            'size' => $this->document->size ?? null,
        ];
    }
}
