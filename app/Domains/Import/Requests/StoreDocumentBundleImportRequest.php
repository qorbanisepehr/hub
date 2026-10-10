<?php

namespace App\Domains\Import\Requests;

use App\Domains\Document\Requests\Concerns\ValidatesDocumentUpload;
use App\Support\Imports\DocumentBundlePattern;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * HTTP boundary for the document-bundle import arm — dry-run and confirm
 * share it. Files come through the browser; the pattern is the user's
 * per-run filename contract and must compile with the named groups the
 * kernel's mapping depends on (422 up front, before any file is touched).
 * File rules and the batch cap follow the single consumer's document
 * config — board meetings — until a second entity arrives.
 */
class StoreDocumentBundleImportRequest extends FormRequest
{
    use ValidatesDocumentUpload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $single = $this->documentUploadRules('board-meeting', withPlacement: false, withMeta: false);

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.(int) config('documents.board-meeting.max_files', 50)],
            'files.*' => $single['file'],
            'pattern' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && trim($value) !== '' && ! DocumentBundlePattern::isValid($value)) {
                        $fail(__('import.document_invalid_pattern'));
                    }
                },
            ],
        ];
    }

    /**
     * The uploaded files in request order.
     *
     * @return list<UploadedFile>
     */
    public function filesList(): array
    {
        $files = $this->file('files', []);

        return is_array($files) ? array_values($files) : [];
    }
}
