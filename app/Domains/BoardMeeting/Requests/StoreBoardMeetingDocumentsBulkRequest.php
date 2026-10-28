<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Requests\Concerns\ResolvesApprovalCategory;
use App\Domains\Document\Requests\Concerns\ValidatesDocumentUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * HTTP boundary for POST /api/board-meetings/{meeting}/documents/bulk.
 *
 * One request imports many مصوب for a meeting (vault idea basket, D30 — the
 * only path new مصوب enter the hub, since every automated path is closed):
 * the user pairs each file with its registry number, the server imports the
 * batch, and the response reports every file instead of failing the whole
 * thing. Validation follows the single-upload boundary — same fixed category,
 * same file types/size, a required `registry_number` per file — and runs up
 * front, so one bad row refuses the request before any file is written. The
 * batch cap comes from the board-meeting document config (`max_files`),
 * which the entity's owner already tunes.
 */
class StoreBoardMeetingDocumentsBulkRequest extends FormRequest
{
    use ResolvesApprovalCategory;
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
            'entries' => ['required', 'array', 'min:1', 'max:'.(int) config('documents.board-meeting.max_files', 50)],
            'entries.*.file' => $single['file'],
            'entries.*.registry_number' => ['required', 'string', 'max:64'],
            'entries.*.title' => ['nullable', 'string', 'max:255'],
            'entries.*.notes' => $single['notes'],
        ];
    }

    /**
     * The validated rows in request order, blank text normalized to null.
     *
     * @return list<array{file: UploadedFile, registry_number: string, title: ?string, notes: ?string}>
     */
    public function entries(): array
    {
        $rows = [];

        foreach ((array) $this->input('entries', []) as $index => $row) {
            $rows[] = [
                'file' => $this->file("entries.{$index}.file"),
                'registry_number' => (string) ($row['registry_number'] ?? ''),
                'title' => $this->blankToNull($row['title'] ?? null),
                'notes' => $this->blankToNull($row['notes'] ?? null),
            ];
        }

        return $rows;
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
