<?php

namespace App\Support\Exports\Render;

use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\Value\Document\DocumentTable;

/**
 * Markdown renderer: the kernel DocumentSpec becomes GitHub-flavoured Markdown.
 *
 * The third document format, and the cheapest one — no font to embed, no
 * pagination, no binary container. What it buys is that a printed meeting
 * becomes a *file* in the way the other two never are: diffable, greppable,
 * reviewable in a pull request, pasteable into an issue or a wiki, and — in a
 * hub whose whole point includes a summarizer — readable by an agent without a
 * parser in between. That last one is why this is worth a renderer at all
 * rather than a `str_replace` on the HTML.
 *
 * Deliberately the plainest of the three. It emits CommonMark plus pipe tables
 * and nothing else, because a document export that only opens in one viewer is
 * a worse export than a PDF that opens everywhere.
 *
 * ON DIRECTION: Markdown has no direction. Persian runs render right-to-left in
 * any viewer that implements the Unicode bidirectional algorithm — which is all
 * of them worth using — but a pipe table's columns are ordered left to right by
 * the table syntax itself, not by the text inside it, so a wide Persian table
 * can read in an order the reader did not choose. There is no way to fix that
 * from inside Markdown: the closest is an HTML wrapper carrying `dir="rtl"`,
 * which buys correctness at the cost of the file no longer being portable
 * Markdown (renderers that strip tags drop the direction, and the plain-text
 * tools that value this format most stop treating it as Markdown at all). So
 * the direction is left to the reader, and said out loud rather than faked.
 */
final class MarkdownRenderer implements DocumentRenderer
{
    public function render(DocumentSpec $spec, $stream): void
    {
        fwrite($stream, $this->markdown($spec));
    }

    public function contentType(): string
    {
        return 'text/markdown; charset=utf-8';
    }

    public function extension(): string
    {
        return 'md';
    }

    private function markdown(DocumentSpec $spec): string
    {
        $out = [];

        if ($spec->title !== '') {
            $out[] = '# '.$this->inline($spec->title);
        }

        if ($spec->subtitle !== '') {
            $out[] = $this->inline($spec->subtitle);
        }

        foreach ($spec->meta as $field) {
            $out[] = '- '.$this->fieldLine($field);
        }

        foreach ($spec->sections as $section) {
            $out = [...$out, ...$this->section($section)];
        }

        // Trimmed and newline-terminated: a file ending in a blank run reads
        // as a mistake in a diff, and most tools want a final newline anyway.
        return rtrim(implode("\n\n", array_filter($out, fn (string $part): bool => trim($part) !== '')))."\n";
    }

    /**
     * @return list<string>
     */
    private function section(DocumentSection $section): array
    {
        if ($section->isEmpty()) {
            return [];
        }

        $out = [];

        if ($section->heading !== '') {
            $out[] = '## '.$this->inline($section->heading);
        }

        foreach ($section->fields as $field) {
            // A field with no label is body prose — the minutes text, a value
            // that stands on its own. Forcing it into a `**:**` pair would
            // invent a label the domain never gave.
            $out[] = $field->label === ''
                ? $this->inline($field->value)
                : $this->fieldLine($field);
        }

        foreach ($section->tables as $table) {
            $out = [...$out, ...$this->table($table)];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function table(DocumentTable $table): array
    {
        if ($table->isEmpty()) {
            return $table->caption === '' ? ['—'] : ['**'.$this->inline($table->caption).'**', '—'];
        }

        $out = [];

        if ($table->caption !== '') {
            $out[] = '**'.$this->inline($table->caption).'**';
        }

        $out[] = '| '.implode(' | ', array_map($this->cell(...), $table->headers)).' |';
        $out[] = '| '.implode(' | ', array_fill(0, count($table->headers), '---')).' |';

        foreach ($table->rows as $row) {
            // Keyed by header index, exactly like the HTML view does: a row that
            // somehow arrived short reads as empty cells rather than shifting
            // every column after the gap.
            $out[] = '| '.implode(' | ', array_map(
                fn (int $index): string => $this->cell($row[$index] ?? ''),
                array_keys($table->headers),
            )).' |';
        }

        return $out;
    }

    private function fieldLine(DocumentField $field): string
    {
        return '**'.$this->inline($field->label).':** '.$this->inline($field->value);
    }

    /**
     * Escape the two characters that would break a pipe table's structure.
     *
     * A backslash must go first or it would escape the backslashes added
     * afterwards. Values often contain neither, but a resolution title with a
     * pipe in it should not silently restructure the row around it.
     */
    private function cell(string $value): string
    {
        return str_replace(['\\', '|', "\r\n", "\n"], ['\\\\', '&#124;', '<br>', '<br>'], $value);
    }

    /**
     * Headings and list items: a newline there would end the line and orphan
     * the rest of the value, so it becomes a space.
     */
    private function inline(string $value): string
    {
        return trim(str_replace(["\r\n", "\n"], ' ', $value));
    }
}
