<?php

namespace App\Support\Exports\Render;

use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\Templates\TemplateFiller;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\Value\Document\DocumentTable;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Word renderer: the kernel DocumentSpec becomes a PHPWord document tree and
 * the Word2007 writer serializes it to docx bytes on the stream.
 *
 * TWO STARTS, ONE BODY. `render()` builds a new document; `composeInto()` adds
 * the same spec to one that already exists — a template supplying fonts, page
 * size, headers and a letterhead. Everything between those two points is one
 * code path, because the only thing a template changes is where the document
 * starts.
 *
 * The original plan avoided a hand-designed .docx in the repo because binary
 * assets cannot be reviewed in a diff and structured output has no one static
 * layout. That still holds for a template in version control — and it stops
 * holding the moment the template is a file a person UPLOADS, where the bytes
 * belong in storage rather than the repo. That path is {@see TemplateFiller},
 * which composes the document with this class and then fills what is around it.
 *
 * a docx only NAMES its font (the reader's Word resolves/falls back), so
 * unlike PDF no TTF embedding is needed — Tahoma ships Persian on every
 * Windows Office install.
 *
 * RTL per element: `bidi` on the paragraph style (w:bidi = right-to-left
 * paragraph) plus `rtl` on the font (w:rtl on the run). Word then shapes and
 * orders Persian text natively.
 */
final class DocxRenderer implements DocumentComposer, DocumentRenderer
{
    public function render(DocumentSpec $spec, $stream): void
    {
        $font = (string) config('exports.documents.docx_font');

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName($font);
        $phpWord->setDefaultFontSize(11);

        $phpWord->addSection(['marginLeft' => 850, 'marginRight' => 850]);

        $this->composeInto($phpWord, $spec);

        $this->emit($phpWord, $stream);
    }

    /**
     * Add the spec to a document that is already open, and leave it open.
     *
     * Appended to the LAST section rather than put in a new one. A new section
     * starts a new page, which would put the meeting on page two of every
     * letterhead with a heading on page one — and "what goes under this" is the
     * whole meaning of a template. The template's own page setup is left exactly
     * as the file declares it.
     */
    public function composeInto(PhpWord $phpWord, DocumentSpec $spec): void
    {
        $font = (string) config('exports.documents.docx_font');
        $sections = $phpWord->getSections();

        // A document with no section at all cannot be appended to, and Word would
        // open the result as malformed. Given one rather than failing: the content
        // is what the caller asked for, and the file had nothing to contribute.
        $section = $sections === []
            ? $phpWord->addSection(['marginLeft' => 850, 'marginRight' => 850])
            : $sections[count($sections) - 1];

        $this->addTitle($section, $spec, $font);
        $this->addMeta($section, $spec, $font);
        $this->addSections($section, $spec, $font);
    }

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function extension(): string
    {
        return 'docx';
    }

    /**
     * Serialise the document and copy it onto the caller's stream.
     *
     * Through a temporary file because the Word2007 writer insists on a path.
     * Both starts go through here rather than repeating the write, so a change to
     * output handling cannot land on one and miss the other.
     *
     * @param  resource  $stream
     */
    private function emit(PhpWord $phpWord, $stream): void
    {
        $path = tempnam(sys_get_temp_dir(), 'hrh-docx-');

        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($path);
            $bytes = file_get_contents($path);

            if ($bytes === false || $bytes === '') {
                throw new \RuntimeException('PHPWord produced no docx output.');
            }

            fwrite($stream, $bytes);
        } finally {
            @unlink($path);
        }
    }

    private function addMeta(Section $section, DocumentSpec $spec, string $font): void
    {
        foreach ($spec->meta as $meta) {
            $this->addLine($section, $meta, $font);
        }
    }

    private function addSections(Section $section, DocumentSpec $spec, string $font): void
    {
        foreach ($spec->sections as $document) {
            $this->addSection($section, $document, $font);
        }
    }

    private function addTitle(Section $section, DocumentSpec $spec, string $font): void
    {
        if ($spec->title !== '') {
            $section->addText(
                $spec->title,
                $this->font(['size' => 18, 'bold' => true], $font),
                $this->paragraph(),
            );
        }

        if ($spec->subtitle !== '') {
            $section->addText(
                $spec->subtitle,
                $this->font(['size' => 12], $font),
                $this->paragraph(),
            );
        }

        $section->addTextBreak();
    }

    private function addSection(Section $section, DocumentSection $document, string $font): void
    {
        $section->addText(
            $document->heading,
            $this->font(['size' => 13, 'bold' => true], $font),
            $this->paragraph(['spaceBefore' => 160, 'spaceAfter' => 80]),
        );

        foreach ($document->fields as $field) {
            $this->addLine($section, $field, $font);
        }

        foreach ($document->tables as $table) {
            $this->addTable($section, $table, $font);
        }

        $section->addTextBreak();
    }

    private function addLine(Section $section, DocumentField $field, string $font): void
    {
        $section->addText(
            $field->label.': '.$field->value,
            $this->font([], $font),
            $this->paragraph(),
        );
    }

    private function addTable(Section $section, DocumentTable $table, string $font): void
    {
        if ($table->isEmpty()) {
            return;
        }

        if ($table->caption !== '') {
            $section->addText(
                $table->caption,
                $this->font(['bold' => true], $font),
                $this->paragraph(['spaceBefore' => 80]),
            );
        }

        $element = $section->addTable([
            'borderSize' => 4,
            'borderColor' => '999999',
            'unit' => 'pct',
            'width' => 100,
            'bidiVisual' => true,
        ]);

        $columnCount = max(1, count($table->headers));
        $cellWidth = (int) floor(5000 / $columnCount);

        $headerRow = $element->addRow();

        foreach ($table->headers as $header) {
            $headerRow->addCell($cellWidth)->addText(
                $header,
                $this->font(['bold' => true], $font),
                $this->paragraph(['alignment' => 'center']),
            );
        }

        foreach ($table->rows as $row) {
            $bodyRow = $element->addRow();

            foreach (array_keys($table->headers) as $index) {
                $bodyRow->addCell($cellWidth)->addText(
                    (string) ($row[$index] ?? ''),
                    $this->font([], $font),
                    $this->paragraph(),
                );
            }
        }

        $section->addTextBreak();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function font(array $extra, string $font): array
    {
        return [...$extra, 'rtl' => true, 'name' => $font];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function paragraph(array $extra = []): array
    {
        return [...['bidi' => true, 'alignment' => 'right'], ...$extra];
    }
}
