<?php

namespace App\Domains\Audit\Events\Document;

use App\Domains\Audit\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class DocumentDownloaded extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $document,
    ) {}

    public function eventName(): string
    {
        return 'document.downloaded';
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
        return "Document {$this->document->getKey()} downloaded";
    }
}
