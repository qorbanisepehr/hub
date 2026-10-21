<?php

namespace App\Domains\BoardMeeting\Events;

use App\Events\BaseAuditEvent;
use App\Support\Exports\Templates\FileTemplate;

/**
 * A template's name or default flag changed.
 *
 * One event for the PATCH, because it is one request and one row: two events a
 * millisecond apart would make a person's single action look like two. The diff
 * says which of the two changed, and `null` means neither did.
 *
 * Naming follows the hub convention (EventCatalogTest): {domain}.{subject}.
 * {action} with single-token segments.
 */
class FileTemplateUpdated extends BaseAuditEvent
{
    public function __construct(
        private readonly FileTemplate $before,
        private readonly FileTemplate $after,
    ) {}

    public function eventName(): string
    {
        return 'boardmeeting.file_template.updated';
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
        return "Export template {$this->after->name()} updated";
    }

    public function metadata(): ?array
    {
        return [
            'key' => $this->after->key(),
            'name' => $this->after->name(),
            'format' => $this->after->format(),
            'domain' => $this->after->domain(),
            'is_default' => $this->after->isDefault(),
        ];
    }

    public function changes(): ?array
    {
        $old = [];
        $new = [];

        if ($this->before->name() !== $this->after->name()) {
            $old['name'] = $this->before->name();
            $new['name'] = $this->after->name();
        }

        if ($this->before->isDefault() !== $this->after->isDefault()) {
            $old['is_default'] = $this->before->isDefault();
            $new['is_default'] = $this->after->isDefault();
        }

        return $old === [] ? null : ['old' => $old, 'new' => $new];
    }
}
