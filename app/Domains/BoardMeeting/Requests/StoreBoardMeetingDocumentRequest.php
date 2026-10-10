<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Requests\Concerns\ResolvesApprovalCategory;
use App\Domains\Document\Requests\Concerns\ValidatesDocumentUpload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload boundary for board-meeting documents (K4 = hub Document system on
 * the board-meeting config key). Placement stays flat: meetings have no
 * sections, so section_key/field_key/meta are not accepted. The category
 * is FIXED (user decision 2026-09-28 — the approvals category is known
 * and constant for this entity): clients do not choose one, and any
 * document_category_id a legacy client still sends is ignored.
 *
 * An upload must name its registry number (vault idea 567, order A): the
 * number is stored on the usage's `metadata` as `registry_number`, the
 * only place a meeting's مصوبات numbers live now that the `approvals`
 * column is gone. `title` is the optional human title of that مصوبه.
 */
class StoreBoardMeetingDocumentRequest extends FormRequest
{
    use ResolvesApprovalCategory;
    use ValidatesDocumentUpload;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->documentUploadRules('board-meeting', withPlacement: false, withMeta: false),
            'registry_number' => ['required', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
