<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Requests\Concerns\ValidatesDocumentUpload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload boundary for board-meeting documents (K4 = hub Document system on
 * the board-meeting config key). Placement stays flat: meetings have no
 * sections, so section_key/field_key/meta are not accepted. The category
 * is FIXED (user decision 2026-09-28 — the approvals category is known
 * and constant for this entity): clients do not choose one, and any
 * document_category_id a legacy client still sends is ignored.
 */
class StoreBoardMeetingDocumentRequest extends FormRequest
{
    use ValidatesDocumentUpload;

    /** The fixed category slug meeting documents belong to. */
    public const FIXED_CATEGORY_SLUG = 'board-meeting-approvals';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->documentUploadRules('board-meeting', withPlacement: false, withMeta: false);
    }

    /** The fixed category, created on first use (no seeding dependency). */
    public function resolvedCategoryId(): int
    {
        return (int) DocumentCategory::query()->firstOrCreate(
            ['slug' => self::FIXED_CATEGORY_SLUG],
            [
                'name' => 'مصوبات جلسات هیئت مدیره',
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'sort_order' => 90,
            ],
        )->id;
    }
}
