<?php

namespace App\Support\Exports\Render;

use App\Support\Exports\Contract\DocumentRenderer;
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
 * Two deliberate deviations from the vault plan: (1) documents are BUILT via
 * the PHPWord API instead of a TemplateProcessor over a hand-designed .docx
 * template — structured profile output has no static placeholder layout, and
 * code-only generation keeps binary template assets out of the repo (the
 * TemplateProcessor path stays reserved for future official-letter forms);
 * (2) a docx only NAMES its font (the reader's Word resolves/falls back), so
 * unlike PDF no TTF embedding is needed — Tahoma ships Persian on every
 * Windows Office install.
 *
 * RTL per element: `bidi` on the paragraph style (w:bidi = right-to-left
 * paragraph) plus `rtl` on the font (w:rtl on the run). Word then shapes and
 * orders Persian text natively.
 */
final class DocxRenderer implements DocumentRenderer
{
    public function render(DocumentSpec $spec, $stream): void
    {
        $font = (string) config('exports.documents.docx_font');

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName($font);
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection(['marginLeft' => 850, 'marginRight' => 850]);

        $this->addTitle($section, $spec, $font);

        foreach ($spec->meta as $meta) {
            $this->addLine($section, $meta, $font);
        }

        foreach ($spec->sections as $document) {
            $this->addSection($section, $document, $font);
        }

        // The writer wants a filesystem path; generate to a temp file and
        // copy the finished bytes onto the kernel's stream.
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

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function extension(): string
    {
        return 'docx';
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
