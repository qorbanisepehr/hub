<?php

namespace App\Domains\BoardMeeting\Support;

use App\Domains\Document\Models\DocumentCategory;

/**
 * The fixed approvals category for board meetings — one source of truth for
 * slug, name, type and sort order, created on first use (no seeding
 * dependency). Upload requests resolve it through the ResolvesApprovalCategory
 * trait; the archive document import resolves it here directly, so every
 * boundary that writes a meeting مصوبه speaks to the same category.
 */
final class BoardMeetingApprovalCategory
{
    public const SLUG = 'board-meeting-approvals';

    public function category(): DocumentCategory
    {
        return DocumentCategory::query()->firstOrCreate(
            ['slug' => self::SLUG],
            [
                'name' => 'مصوبات جلسات هیئت مدیره',
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'sort_order' => 90,
            ],
        );
    }

    public function id(): int
    {
        return (int) $this->category()->id;
    }
}
