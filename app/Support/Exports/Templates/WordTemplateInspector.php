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
     * Only the parts that can REFUSE an upload are reported. It used to also
     * report whether the file held a placeholder, which decided whether the
     * template replaced the generated document or merely framed it; that
     * classification is gone, so scanning the XML for `{{` would be reading a
     * file in order to store a fact nothing asks for any more.
     *
     * @return array{size: int}
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

            return ['size' => $size];
        } finally {
            $zip->close();
        }
    }
}
