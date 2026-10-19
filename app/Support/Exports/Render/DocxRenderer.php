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
    public function __construct(
        private readonly DocxRtl $rtl = new DocxRtl,
    ) {}

    public function render(DocumentSpec $spec, $stream): void
    {
        $font = (string) config('exports.documents.docx_font');

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName($font);
        $phpWord->setDefaultFontSize(11);

        $phpWord->addSection(['marginLeft' => 850, 'marginRight' => 850]);

        // PHPWord's own RTL default, set for the duration and put back afterwards:
        // see DocxRtl for why it is a static and why it must not be left set.
        $this->rtl->with(function () use ($phpWord, $spec, $stream): void {
            $this->composeInto($phpWord, $spec, DocxBodyStyle::Ours);

            $this->emit($phpWord, $stream);
        });
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
    public function composeInto(PhpWord $phpWord, DocumentSpec $spec, DocxBodyStyle $style): void
    {
        $font = (string) config('exports.documents.docx_font');
        $sections = $phpWord->getSections();

        // A document with no section at all cannot be appended to, and Word would
        // open the result as malformed. Given one rather than failing: the content
        // is what the caller asked for, and the file had nothing to contribute.
        $section = $sections === []
            ? $phpWord->addSection(['marginLeft' => 850, 'marginRight' => 850])
            : $sections[count($sections) - 1];

        $this->addTitle($section, $spec, $font, $style);
        $this->addMeta($section, $spec, $font, $style);
        $this->addSections($section, $spec, $font, $style);
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

            // PHPWord cannot express a right-to-left section, so it is written
            // afterwards. See {@see DocxSectionDirection} for why every paragraph
            // saying right is not the same as the document being right.
            DocxPackage::at($path)->makeSectionRtl();

            $bytes = file_get_contents($path);

            if ($bytes === false || $bytes === '') {
                throw new \RuntimeException('PHPWord produced no docx output.');
            }

            fwrite($stream, $bytes);
        } finally {
            @unlink($path);
        }
    }

    private function addMeta(Section $section, DocumentSpec $spec, string $font, DocxBodyStyle $style): void
    {
        foreach ($spec->meta as $meta) {
            $this->addLine($section, $meta, $font, $style);
        }
    }

    private function addSections(Section $section, DocumentSpec $spec, string $font, DocxBodyStyle $style): void
    {
        foreach ($spec->sections as $document) {
            $this->addSection($section, $document, $font, $style);
        }
    }

    private function addTitle(Section $section, DocumentSpec $spec, string $font, DocxBodyStyle $style): void
    {
        if ($spec->title !== '') {
            $section->addText(
                $this->xml($spec->title),
                $this->font(['size' => 18, 'bold' => true], $font, $style),
                $this->paragraph(),
            );
        }

        if ($spec->subtitle !== '') {
            $section->addText(
                $this->xml($spec->subtitle),
                $this->font(['size' => 12], $font, $style),
                $this->paragraph(),
            );
        }

        $section->addTextBreak();
    }

    private function addSection(Section $section, DocumentSection $document, string $font, DocxBodyStyle $style): void
    {
        $section->addText(
            $this->xml($document->heading),
            $this->font(['size' => 13, 'bold' => true], $font, $style),
            $this->paragraph(['spaceBefore' => 160, 'spaceAfter' => 80]),
        );

        foreach ($document->fields as $field) {
            $this->addLine($section, $field, $font, $style);
        }

        foreach ($document->tables as $table) {
            $this->addTable($section, $table, $font, $style);
        }

        $section->addTextBreak();
    }

    private function addLine(Section $section, DocumentField $field, string $font, DocxBodyStyle $style): void
    {
        $section->addText(
            $this->xml($field->label.': '.$field->value),
            $this->font([], $font, $style),
            $this->paragraph(),
        );
    }

    /**
     * Make a value safe to sit inside the document XML.
     *
     * PHPWord writes a `Text` element's content into `<w:t>` **unescaped**, so
     * anything in a value that reads as markup is read as markup: `<ب>` is
     * parsed as a tag and disappears, taking whatever followed it. The document
     * still builds and still opens — the text is simply shorter than the value,
     * with nothing to say so.
     *
     * This is the ordinary generated export as well as the template one: meeting
     * minutes are free text, frequently imported from scanned documents, where
     * `<` and `&` are punctuation rather than markup. Losing a clause of the
     * minutes to a stray bracket is not a cosmetic problem.
     *
     * Per-renderer on purpose. A `DocumentSpec` is also read by the Markdown and
     * JSON renderers, where escaping for XML would put `&amp;` into a JSON
     * payload and `&lt;` into a Markdown file. The value is plain text until
     * something writes it into a format, and that something owns the escaping.
     */
    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function addTable(Section $section, DocumentTable $table, string $font, DocxBodyStyle $style): void
    {
        if ($table->isEmpty()) {
            return;
        }

        if ($table->caption !== '') {
            $section->addText(
                $this->xml($table->caption),
                $this->font(['bold' => true], $font, $style),
                $this->paragraph(['spaceBefore' => 80]),
            );
        }

        $element = $section->addTable($this->rtl->table([
            'borderSize' => 4,
            'borderColor' => '999999',
            'unit' => 'pct',
            'width' => 100,
        ]));

        $columnCount = max(1, count($table->headers));
        $cellWidth = (int) floor(5000 / $columnCount);

        $headerRow = $element->addRow();

        foreach ($table->headers as $header) {
            $headerRow->addCell($cellWidth)->addText(
                $this->xml($header),
                $this->font(['bold' => true], $font, $style),
                $this->paragraph(['alignment' => 'center']),
            );
        }

        foreach ($table->rows as $row) {
            $bodyRow = $element->addRow();

            foreach (array_keys($table->headers) as $index) {
                $bodyRow->addCell($cellWidth)->addText(
                    $this->xml((string) ($row[$index] ?? '')),
                    $this->font([], $font, $style),
                    $this->paragraph(),
                );
            }
        }

        $section->addTextBreak();
    }

    /**
     * The run's own appearance.
     *
     * Everything here that is not about direction is the caller's decision, and the
     * direction itself is {@see DocxRtl}'s — including why it is not simply
     * `alignment: right`.
     *
     * A docx only NAMES its font — the reader's Word resolves it — so naming Tahoma
     * over a template that says B Nazanin does not produce B Nazanin. It produces a
     * document with two typefaces in it, which is the visible half of "the style
     * broke" and the reason {@see DocxBodyStyle} exists.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function font(array $extra, string $font, DocxBodyStyle $style): array
    {
        if ($style === DocxBodyStyle::Inherited) {
            // Size goes with the family. Leaving an 18pt title over 11pt body text
            // in a file that never asked for either is the same mistake one level
            // up: a type scale imposed on a document that has its own.
            unset($extra['size']);
            unset($extra['name']);

            return $this->rtl->run($extra);
        }

        return $this->rtl->run($extra, $font);
    }

    /**
     * The paragraph's direction and alignment, which are {@see DocxRtl}'s.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function paragraph(array $extra = []): array
    {
        return $this->rtl->paragraph($extra);
    }
}
