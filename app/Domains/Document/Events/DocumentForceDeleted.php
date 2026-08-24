<?php

namespace App\Domains\Document\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class DocumentForceDeleted extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $document,
    ) {}

    public function eventName(): string
    {
        return 'document.force_deleted';
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
            'title' => $this->document->title ?? null,
        ];
    }

    public function description(): ?string
    {
        return "Document {$this->document->getKey()} force deleted";
    }
}
