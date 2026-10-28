<?php

namespace App\Domains\BoardMeeting\Events;

use App\Events\BaseAuditEvent;
use App\Support\Exports\Templates\FileTemplate;

/**
 * A template file was uploaded or replaced.
 *
 * The template carries the board's letterhead, so "who put this file here, and
 * when" is the question the audit log exists to answer. The file itself is not
 * in the event: the row and the bytes are the subject, and pasting a document
 * into the log would duplicate it under a second set of retention rules.
 *
 * Naming follows the hub convention (EventCatalogTest): {domain}.{subject}.
 * {action} with single-token segments.
 */
class FileTemplateUploaded extends BaseAuditEvent
{
    public function __construct(
        private readonly FileTemplate $template,
        private readonly bool $replaced = false,
    ) {}

    public function eventName(): string
    {
        return 'boardmeeting.file_template.uploaded';
    }

    public function category(): string
    {
        return 'boardmeeting';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'file-template',
            // A settings row has a string key, not a numeric id; the key travels
            // in `metadata()` because `subject_id` is a bigint column.
            'id' => null,
        ];
    }

    public function description(): ?string
    {
        return "Export template {$this->template->name()} ".($this->replaced ? 'replaced' : 'uploaded');
    }

    public function metadata(): ?array
    {
        return [
            'key' => $this->template->key(),
            'name' => $this->template->name(),
            'format' => $this->template->format(),
            'domain' => $this->template->domain(),
            'is_default' => $this->template->isDefault(),
            'size' => $this->template->size(),
            // A replacement is the same event because it is the same write — new
            // bytes for a template — but the log has to say which one happened.
            'replaced' => $this->replaced,
        ];
    }
}
