<?php

namespace App\Domains\BoardMeeting\Requests\Concerns;

use App\Domains\BoardMeeting\Support\BoardMeetingApprovalCategory;

/**
 * The approvals category is fixed for board meetings (user decision
 * 2026-09-28): clients never choose one, and any `document_category_id` a
 * legacy client still sends is ignored. Upload requests resolve it from the
 * one source of truth ({@see BoardMeetingApprovalCategory}), which creates
 * the category on first use — no seeding dependency.
 */
trait ResolvesApprovalCategory
{
    /** The fixed category slug meeting documents belong to. */
    public const FIXED_CATEGORY_SLUG = BoardMeetingApprovalCategory::SLUG;

    /** The fixed category, created on first use (no seeding dependency). */
    public function resolvedCategoryId(): int
    {
        return app(BoardMeetingApprovalCategory::class)->id();
    }
}
