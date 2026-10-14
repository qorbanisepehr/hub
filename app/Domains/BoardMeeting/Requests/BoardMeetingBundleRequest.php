<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Contracts\Authorization;
use App\Support\Exports\DocumentRendererRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The board-meeting-specific half of POST /api/board-meetings/export/bundle.
 *
 * Two parts, gated separately — which is the whole reason this is a
 * FormRequest rather than an options array on the controller:
 *
 *  - **Sheets** (the chosen formats) need `board-meeting.export`, already the
 *    route's middleware.
 *  - **Attachments** need `board-meeting.documents.download`, checked here
 *    because only this request knows whether they were asked for. An
 *    export-only reader can therefore still take a PDF bundle of their own
 *    meetings — they are not blocked by a permission guarding files the bundle
 *    does not contain.
 *
 * The format list is checked against the renderer registry rather than a
 * hardcoded list, so adding a renderer makes it available here with no second
 * edit.
 */
class BoardMeetingBundleRequest extends FormRequest
{
    /** The permission that guards a meeting's own files. */
    private const DOCUMENTS_DOWNLOAD = 'board-meeting.documents.download';

    /** Refuses an unbounded `ids` list before it becomes a query. */
    private const MAX_SELECTION = 500;

    public function __construct(
        private readonly DocumentRendererRegistry $renderers,
        private readonly Authorization $authorization,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        // The sheets are already gated by the route's `board-meeting.export`.
        if (! $this->wantsAttachments()) {
            return true;
        }

        return $this->authorization->can($actor, self::DOCUMENTS_DOWNLOAD);
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'formats' => ['required', 'array', 'min:1'],
            'formats.*' => ['string', Rule::in($this->renderers->allFormats())],
            'documents' => ['sometimes', 'boolean'],
            // A selection is optional: without one the bundle covers everything
            // the list endpoint can show under the same filters, which is what
            // "export all I am looking at" means.
            'ids' => ['sometimes', 'array', 'max:'.self::MAX_SELECTION],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'formats.*.in' => __('messages.validation.unsupported_format'),
        ];
    }

    /**
     * @return list<string>
     */
    public function bundleFormats(): array
    {
        return array_values(array_filter(
            array_map(
                fn ($format) => is_string($format) ? trim($format) : '',
                (array) $this->input('formats', []),
            ),
            static fn (string $format): bool => $format !== '',
        ));
    }

    public function wantsAttachments(): bool
    {
        return $this->boolean('documents');
    }

    /**
     * @return list<int>
     */
    public function selectedIds(): array
    {
        return array_values(array_unique(array_map(
            'intval',
            (array) $this->input('ids', []),
        )));
    }
}
