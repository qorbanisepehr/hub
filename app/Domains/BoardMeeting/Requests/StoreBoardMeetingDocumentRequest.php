<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\Document\Requests\Concerns\ValidatesDocumentUpload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload boundary for board-meeting documents (K4 = hub Document system on
 * the board-meeting config key). Placement stays flat: meetings have no
 * sections, so section_key/field_key/meta are not accepted.
 */
class StoreBoardMeetingDocumentRequest extends FormRequest
{
    use ValidatesDocumentUpload;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_category_id' => ['required', 'exists:document_categories,id'],
            ...$this->documentUploadRules('board-meeting', withPlacement: false, withMeta: false),
        ];
    }
}
