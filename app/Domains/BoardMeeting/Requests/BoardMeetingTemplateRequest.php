<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Support\Exports\Templates\TemplateResolver;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The sections a printed meeting should contain, in the order it should.
 *
 * Validated against the sheet's OWN vocabulary rather than against "any
 * non-empty string", which is all the stored setting accepts. The asymmetry is
 * deliberate and is the whole point of this class existing:
 *
 *  - A template being WRITTEN NOW is a typo, and a typo deserves a 422 that
 *    names the legal sections. Somebody looking at a list of checkboxes and
 *    submitting `agenda_item` should be told, not left wondering why their
 *    section never appears in the PDF.
 *  - A template written by a NEWER deploy must survive being read by this one,
 *    so the stored form tolerates names this version does not have — see
 *    {@see TemplateResolver}, which drops them
 *    and renders the rest.
 *
 * Leniency on the read, strictness on the write. That is what lets a rollback
 * print something slightly wrong instead of nothing at all.
 */
class BoardMeetingTemplateRequest extends FormRequest
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
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [
                'required',
                'string',
                'distinct',
                'in:'.implode(',', $this->legalSections()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sections.*.in' => __('board_meeting.template.unknown_section', [
                'sections' => implode('، ', $this->legalSections()),
            ]),
            'sections.*.distinct' => __('board_meeting.template.duplicate_section'),
        ];
    }

    /**
     * @return list<string>
     */
    public function sections(): array
    {
        return array_values(array_map(
            fn (mixed $section): string => (string) $section,
            (array) $this->input('sections', []),
        ));
    }

    /**
     * The section names this sheet can print.
     *
     * Read off the export itself rather than from a constant kept beside it. A
     * list maintained next to the thing it describes is a list that can fall
     * behind it, and the failure mode is a section this app renders and prints
     * but that no template is able to name — a validator quietly making part of
     * the document unconfigurable.
     *
     * @return list<string>
     */
    private function legalSections(): array
    {
        return app(BoardMeetingSheetExport::class)->sectionNames();
    }
}
