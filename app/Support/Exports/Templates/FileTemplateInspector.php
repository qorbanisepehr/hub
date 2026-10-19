<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Exceptions\InvalidFileTemplate;
use App\Support\Exports\Render\DocxPackage;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * What an uploaded `.docx` actually is, before it is stored.
 *
 * A `.docx` is a zip of XML, so "it ends in .docx" says nothing about what is in
 * it. This checks the parts that matter and refuses the ones that must never be
 * written, because a template is a file the application will later OPEN and
 * rewrite — handing it something unexamined makes every export a re-run of
 * whatever the upload contained.
 *
 * MACROS ARE REFUSED OUTRIGHT. `word/vbaProject.bin` is how a `.docm` keeps its
 * macros while claiming to be a `.docx`, and a macro-enabled document is one the
 * reader's Word will offer to run. There is no version of this feature that needs
 * one, so the check is not configurable.
 *
 * SIZE IS CHECKED HERE even though PHP already caps uploads. That cap is a
 * whole-request number shared with every other upload in the application; a
 * generous multipart setting should not be the only thing standing between a
 * person and a ten-megabyte write per template, repeated.
 *
 * The archive is also required to CONTAIN the parts a document is made of.
 * Without `word/document.xml` the file would store happily and fail at export
 * time, in a Word library, with an error nobody can act on — so it is refused
 * here where the message can name the file.
 */
final class FileTemplateInspector
{
    /** The one part without which the file is not a document. */
    private const DOCUMENT_PART = 'word/document.xml';

    /** Macro storage. A `.docx` has none; a renamed `.docm` does. */
    private const MACRO_PART = 'word/vbaProject.bin';

    /**
     * The size cap, read at inspection time.
     *
     * Deliberately not taken in the constructor. A singleton built from config
     * when the container first resolves it freezes the value for the life of the
     * process, so the one thing this class exists to enforce becomes unchangeable
     * for the rest of the request — and untestable without tearing the container
     * down. Reading it here keeps it one place AND keeps it live.
     */
    private function maxBytes(): int
    {
        return (int) config('exports.word.max_bytes');
    }

    /**
     * Check the file for the format it claims to be, and report what is in it.
     *
     * The format decides what "a file that is not what it says" even means. For
     * `.docx` that is a zip of XML with a `word/document.xml` inside, and it can
     * carry macros; for Markdown and JSON the file is plain text, the zip checks
     * are nonsense, and the question is whether it parses at all. Doing the docx
     * checks on a Markdown file would refuse every one of them.
     *
     * IT REPORTS THE KIND AND THE PLACEHOLDERS, and that is not a nicety. Whether
     * the generated meeting is written into a template at all is
     * {@see TemplateKind}'s decision, read off the file, so the person uploading
     * one is the only one who can find out — the export is the first moment the
     * answer becomes visible, and by then it is a document somebody has to open to
     * find out they got the other kind. A rule that is applied silently is a rule
     * that gets reported as a bug.
     *
     * @return array{size: int, kind: string, placeholders: list<string>}
     *
     * @throws InvalidFileTemplate
     */
    public function inspect(UploadedFile $file, string $format = 'docx'): array
    {
        $size = (int) $file->getSize();
        $limit = $this->maxBytes();

        if ($size <= 0) {
            throw InvalidFileTemplate::because('فایل خالی است.');
        }

        if ($size > $limit) {
            throw InvalidFileTemplate::tooLarge($size, $limit);
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            throw InvalidFileTemplate::because('فایل بارگذاری‌شده خوانده نشد.');
        }

        if ($format !== 'docx') {
            // Plain text. The only thing worth refusing is a file that is not
            // text at all, which shows up as unreadable bytes rather than as a
            // parse failure; anything stricter belongs to the format's own
            // filler, which sees the file after it is stored.
            if (! mb_check_encoding((string) file_get_contents($path), 'UTF-8')) {
                throw InvalidFileTemplate::because('فایل متنی با کدگذاری UTF-8 نیست.');
            }

            return [
                'size' => $size,
                ...$this->describe((string) file_get_contents($path)),
            ];
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw InvalidFileTemplate::because('فایل یک آرشیو معتبر Word نیست.');
        }

        try {
            if ($zip->locateName(self::MACRO_PART) !== false) {
                // Named plainly: the person uploading needs to know the file is
                // rejected for carrying macros, not that "something was wrong".
                throw InvalidFileTemplate::because(
                    'این فایل ماکرو دارد و پذیرفته نمی‌شود؛ قالب باید یک سند Word بدون ماکرو باشد.',
                );
            }

            if ($zip->locateName(self::DOCUMENT_PART) === false) {
                throw InvalidFileTemplate::because(
                    'این فایل یک سند Word نیست؛ بخش اصلی آن یافت نشد.',
                );
            }

            return [
                'size' => $size,
                ...$this->describeDocx($path),
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * The kind and placeholders of a Word file.
     *
     * Read from the zip rather than by loading the document, because loading is
     * only half of it: a reader that saves what it loaded would rewrite the
     * template, and there is nothing to be gained by going near the writer on a path
     * whose whole job is to describe a file. {@see DocxPackage} joins a macro Word
     * split across runs by concatenating the text of each paragraph, which is the
     * same repair `TemplateProcessor` makes on its own.
     *
     * @return array{kind: string, placeholders: list<string>}
     */
    private function describeDocx(string $path): array
    {
        $names = DocxPackage::at($path)->names();

        return [
            'kind' => TemplateKind::fromNames($names)->value,
            'placeholders' => $names,
        ];
    }

    /**
     * The kind and placeholders of a plain-text file.
     *
     * @return array{kind: string, placeholders: list<string>}
     */
    private function describe(string $text): array
    {
        $names = $this->textMacros($text);

        return [
            'kind' => TemplateKind::fromNames($names)->value,
            'placeholders' => $names,
        ];
    }

    /**
     * @return list<string>
     */
    private function textMacros(string $text): array
    {
        preg_match_all('/\{\{(.+?)\}\}/s', $text, $matches);

        $names = [];

        foreach ($matches[1] ?? [] as $name) {
            $name = trim($name);

            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }
}
