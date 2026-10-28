<?php

namespace App\Support\Exports\Render;

use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\Value\Document\DocumentSpec;

/**
 * JSON renderer: the kernel DocumentSpec becomes a machine-readable document.
 *
 * The format the hub exists for. Every other renderer here produces something a
 * *person* reads — a PDF you print, a Word file you edit, Markdown you skim —
 * and every one of them is a dead end for a system on the other side of an
 * import. JSON is what nearly every other system already speaks, and until this
 * existed a CV, an employee profile, a questionnaire and a board meeting were
 * all invisible to it.
 *
 * This is also the reason registering it is worth more than it looks: the
 * registry resolves the format per request, so one line in `AppServiceProvider`
 * gives this to every domain that implements `DocumentSource`, on the day it is
 * added, with no domain touched.
 *
 * LOSSLESS ON PURPOSE. The output mirrors the DocumentSpec exactly — same
 * headings, same field labels and values, same table headers and cells, same
 * order — so another system can rebuild the document rather than read a summary
 * of it. A lossy "nice" JSON shape would be a second contract to keep in step
 * with the first one, and would quietly drop anything it did not anticipate;
 * that is the opposite of an interchange format. The only thing added is
 * `generated_at`, which nothing can be reconstructed from and every consumer
 * wants to know.
 *
 * Not pretty-printed. A JSON export is read by machines, and whitespace is size.
 * Clients that want it legible can pipe it through a formatter.
 */
final class JsonRenderer implements DocumentRenderer
{
    private const VERSION = 1;

    public function render(DocumentSpec $spec, $stream): void
    {
        $json = json_encode(
            $this->payload($spec),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        fwrite($stream, $json.PHP_EOL);
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    public function extension(): string
    {
        return 'json';
    }

    /**
     * The document as data, in the shape a reader on the other end can rebuild.
     *
     * @return array<string, mixed>
     */
    public function payload(DocumentSpec $spec): array
    {
        return [
            'version' => self::VERSION,
            'title' => $spec->title,
            'subtitle' => $spec->subtitle,
            'meta' => array_map(
                static fn ($field): array => [
                    'label' => $field->label,
                    'value' => $field->value,
                ],
                $spec->meta,
            ),
            'sections' => array_map(
                fn ($section): array => [
                    'heading' => $section->heading,
                    'fields' => array_map(
                        static fn ($field): array => [
                            'label' => $field->label,
                            'value' => $field->value,
                        ],
                        $section->fields,
                    ),
                    'tables' => array_map(
                        static fn ($table): array => [
                            'caption' => $table->caption,
                            'headers' => $table->headers,
                            'rows' => $table->rows,
                        ],
                        $section->tables,
                    ),
                ],
                $spec->sections,
            ),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
