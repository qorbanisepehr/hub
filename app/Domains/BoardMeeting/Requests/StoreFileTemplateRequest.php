<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Support\Exports\Templates\FileTemplateInspector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Uploading a template file for one domain.
 *
 * The file itself is not validated here — whether a zip carries a macro is a
 * question about the archive, and {@see FileTemplateInspector}
 * is the only thing that opens one. This request owns the fields a person filled
 * in, and the inspector owns what the file is.
 *
 * The domain is checked against what a template actually exists for rather than
 * against a list kept here, because that list would be the second place
 * remembering which domains have documents and the first one to go stale.
 */
class StoreFileTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['required', 'string', Rule::in($this->allowedDomains())],
            'is_default' => ['sometimes', 'boolean'],
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
            'domain.in' => __('board_meeting.template.unknown_domain', [
                'domains' => implode('، ', $this->allowedDomains()),
            ]),
        ];
    }

    public function domain(): string
    {
        return (string) $this->input('domain');
    }

    public function name(): string
    {
        return (string) $this->input('name');
    }

    public function isDefault(): bool
    {
        return $this->boolean('is_default');
    }

    /**
     * @return list<string>
     */
    private function allowedDomains(): array
    {
        return [BoardMeetingSheetExport::TEMPLATE_DOMAIN];
    }
}
