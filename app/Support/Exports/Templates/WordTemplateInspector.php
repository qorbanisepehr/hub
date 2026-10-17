<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Exceptions\InvalidWordTemplate;
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
final class WordTemplateInspector
{
    /** The one part without which the file is not a document. */
    private const DOCUMENT_PART = 'word/document.xml';

    /** Macro storage. A `.docx` has none; a renamed `.docm` does. */
    private const MACRO_PART = 'word/vbaProject.bin';

    /**
     * Parts whose text can hold a placeholder.
     *
     * Headers and footers are included because a template author puts the
     * meeting's title in a header about as often as in the body, and a
     * placeholder found there is one this application can fill.
     */
    private const TEXT_PARTS = '/^word\/(document|header\d*|footer\d*)\.xml$/';

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
     * Check the file and report what it is.
     *
     * @return array{has_placeholders: bool, size: int}
     *
     * @throws InvalidWordTemplate
     */
    public function inspect(UploadedFile $file): array
    {
        $size = (int) $file->getSize();
        $limit = $this->maxBytes();

        if ($size <= 0) {
            throw InvalidWordTemplate::because('فایل خالی است.');
        }

        if ($size > $limit) {
            throw InvalidWordTemplate::tooLarge($size, $limit);
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            throw InvalidWordTemplate::because('فایل بارگذاری‌شده خوانده نشد.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw InvalidWordTemplate::because('فایل یک آرشیو معتبر Word نیست.');
        }

        try {
            if ($zip->locateName(self::MACRO_PART) !== false) {
                // Named plainly: the person uploading needs to know the file is
                // rejected for carrying macros, not that "something was wrong".
                throw InvalidWordTemplate::because(
                    'این فایل ماکرو دارد و پذیرفته نمی‌شود؛ قالب باید یک سند Word بدون ماکرو باشد.',
                );
            }

            if ($zip->locateName(self::DOCUMENT_PART) === false) {
                throw InvalidWordTemplate::because(
                    'این فایل یک سند Word نیست؛ بخش اصلی آن یافت نشد.',
                );
            }

            return [
                'has_placeholders' => $this->containsPlaceholder($zip),
                'size' => $size,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * Whether the document asks to be filled.
     *
     * This one fact decides how the template is used for its whole life: with a
     * placeholder it is a content template and the file decides what the output
     * says; without one it is a style template and generated content is added to
     * it. Recording it at upload is what lets an export choose without opening
     * the file, and it cannot go stale because the file only changes when the
     * upload is repeated.
     */
    private function containsPlaceholder(ZipArchive $zip): bool
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (preg_match(self::TEXT_PARTS, $name) !== 1) {
                continue;
            }

            $xml = $zip->getFromIndex($index);

            if ($xml !== false && str_contains($xml, '{{')) {
                return true;
            }
        }

        return false;
    }
}
