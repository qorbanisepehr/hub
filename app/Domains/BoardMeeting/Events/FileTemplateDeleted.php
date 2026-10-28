<?php

namespace App\Domains\BoardMeeting\Events;

use App\Events\BaseAuditEvent;
use App\Support\Exports\Templates\FileTemplate;

/**
 * A template was removed from the library.
 *
 * The file goes with the row, so after a delete the audit log is the only place
 * left that says which template a meeting was exported with before somebody
 * pulled it. The subject carries the name and key rather than the bytes: an
 * event that pasted the file would duplicate it under a second retention rule.
 *
 * Naming follows the hub convention (EventCatalogTest): {domain}.{subject}.
 * {action} with single-token segments.
 */
class FileTemplateDeleted extends BaseAuditEvent
{
    public function __construct(
        private readonly FileTemplate $template,
    ) {}

    public function eventName(): string
    {
        return 'boardmeeting.file_template.deleted';
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
        return "Export template {$this->template->name()} deleted";
    }

    public function metadata(): ?array
    {
        return [
            'key' => $this->template->key(),
            'name' => $this->template->name(),
            'format' => $this->template->format(),
            'domain' => $this->template->domain(),
            'is_default' => $this->template->isDefault(),
        ];
    }
}
