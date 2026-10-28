<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Support\Exports\Templates\FileTemplateInspector;
use App\Support\Exports\Templates\TemplateFillerRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
 *
 * THE FORMAT IS DERIVED FROM THE FILE, NOT ASKED FOR, and this used to be the
 * second thing that silently produced an unusable template. There was no `format`
 * field at all: the controller defaulted to `docx` for anything it was not told
 * otherwise, the client never told it otherwise, so a `.md` upload was stored as a
 * `docx` and then refused — the inspector opening Markdown as a zip and reporting
 * that it was not a Word document. The `accept` attribute had been widened to offer
 * `.md` and `.json`, so the form actively invited a file it could not accept.
 *
 * Taken from the file name because that is the only thing the person uploading did
 * not have to be asked about, and because a format the caller states can disagree
 * with the file it claims to be. The name is still verified against what is actually
 * registered, so a format nobody has a filler for is refused at the form rather than
 * at the first export somebody attempts.
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
            'format' => ['sometimes', 'string', Rule::in($this->allowedFormats())],
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
            'format.in' => __('board_meeting.template.unknown_format', [
                'formats' => implode('، ', $this->allowedFormats()),
            ]),
        ];
    }

    /**
     * The format to store this template as.
     *
     * Stated by the caller wins when it agrees with the file's own extension, and is
     * ignored when it does not: the extension is the evidence, and a form that
     * disagreed with its own file would be a form that stores something it cannot
     * read back.
     *
     * @throws ValidationException
     */
    public function templateFormat(): string
    {
        $fromName = $this->formatOfFileName();

        if ($fromName === null) {
            // The replacements are passed here rather than left to the validator:
            // withMessages takes the finished sentence, so an un-replaced :formats would
            // be shown to the person exactly as written.
            throw ValidationException::withMessages([
                'file' => __('board_meeting.template.unknown_extension', [
                    'formats' => implode('، ', $this->allowedFormats()),
                ]),
            ]);
        }

        $claimed = $this->input('format');

        return is_string($claimed) && $claimed !== '' ? $claimed : $fromName;
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
     * The registered format this file's extension names, or null when it names
     * nothing we can fill.
     */
    private function formatOfFileName(): ?string
    {
        $name = (string) ($this->file('file')?->getClientOriginalName() ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === '') {
            return null;
        }

        return app(TemplateFillerRegistry::class)->has($extension) ? $extension : null;
    }

    /**
     * @return list<string>
     */
    private function allowedDomains(): array
    {
        return [BoardMeetingSheetExport::TEMPLATE_DOMAIN];
    }

    /**
     * Every format a template may be stored as.
     *
     * Asked of the registry rather than listed here, so adding a filler is all it
     * takes for its format to become uploadable — and so the error a person sees names
     * what actually works.
     *
     * @return list<string>
     */
    private function allowedFormats(): array
    {
        return app(TemplateFillerRegistry::class)->formats();
    }
}
