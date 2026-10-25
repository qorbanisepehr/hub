<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Support\Exports\Templates\FileTemplateLibrary;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Replacing the bytes of an existing template.
 *
 * The file is the only field. Its format is not asked for: the template's own
 * key already decides that, and {@see FileTemplateLibrary::replace()}
 * refuses a file whose extension disagrees with it.
 */
class ReplaceFileTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('board_meeting.template.file_required'),
            'file.file' => __('board_meeting.template.file_must_be_file'),
        ];
    }
}
