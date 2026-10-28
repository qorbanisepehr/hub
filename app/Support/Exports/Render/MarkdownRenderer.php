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

    /**
     * Assembles the document as BLOCKS, one per paragraph, separated by a
     * blank line.
     *
     * The separation is the whole design, not a formatting preference. A GFM
     * table ends at the first blank line, so a table's rows must travel as ONE
     * block with single newlines inside it; handing each row out as its own
     * block produces a document where every `|` line is a separate paragraph
     * and no reader renders a table at all.
     *
     * @return list<string>
     */
    private function markdown(DocumentSpec $spec): string
    {
        $blocks = [];

        if ($spec->title !== '') {
            $blocks[] = '# '.$this->inline($spec->title);
        }

        if ($spec->subtitle !== '') {
            $blocks[] = $this->inline($spec->subtitle);
        }

        foreach ($spec->meta as $field) {
            $blocks[] = '- '.$this->fieldLine($field);
        }

        foreach ($spec->sections as $section) {
            $blocks = [...$blocks, ...$this->section($section)];
        }

        // Trimmed and newline-terminated: a file ending in a blank run reads
        // as a mistake in a diff, and most tools want a final newline anyway.
        return rtrim(implode("\n\n", array_filter($blocks, fn (string $block): bool => trim($block) !== '')))."\n";
    }

    /**
     * @return list<string>
     */
    private function section(DocumentSection $section): array
    {
        if ($section->isEmpty()) {
            return [];
        }

        $blocks = [];

        if ($section->heading !== '') {
            $blocks[] = '## '.$this->inline($section->heading);
        }

        foreach ($section->fields as $field) {
            // A field with no label is body prose — the minutes text, a value
            // that stands on its own. Forcing it into a `**:**` pair would
            // invent a label the domain never gave.
            $blocks[] = $field->label === ''
                ? $this->inline($field->value)
                : $this->fieldLine($field);
        }

        foreach ($section->tables as $table) {
            $blocks = [...$blocks, ...$this->table($table)];
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function table(DocumentTable $table): array
    {
        if ($table->isEmpty()) {
            return $table->caption === ''
                ? ['—']
                : ['**'.$this->inline($table->caption).'**', '—'];
        }

        $lines = [];

        if ($table->caption !== '') {
            $lines[] = '**'.$this->inline($table->caption).'**';
            // The caption is inside the block so it sits directly above the
            // table it names, rather than a blank line away from it.
            $lines[] = '';
        }

        $lines[] = '| '.implode(' | ', array_map($this->cell(...), $table->headers)).' |';
        $lines[] = '| '.implode(' | ', array_fill(0, count($table->headers), '---')).' |';

        foreach ($table->rows as $row) {
            // Keyed by header index, exactly like the HTML view does: a row that
            // somehow arrived short reads as empty cells rather than shifting
            // every column after the gap.
            $lines[] = '| '.implode(' | ', array_map(
                fn (int $index): string => $this->cell($row[$index] ?? ''),
                array_keys($table->headers),
            )).' |';
        }

        // ONE block, lines joined by a bare newline: this is what makes it a
        // table rather than a run of pipe-shaped paragraphs.
        return [implode("\n", $lines)];
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
     *
     * Delegated to {@see MarkdownText} so the template filler, which writes
     * values into the same kind of tables, cannot disagree about how.
     */
    private function cell(string $value): string
    {
        return MarkdownText::cell($value);
    }

    /**
     * Headings and list items: a newline there would end the line and orphan
     * the rest of the value, so it becomes a space.
     */
    private function inline(string $value): string
    {
        return MarkdownText::inline($value);
    }
}
