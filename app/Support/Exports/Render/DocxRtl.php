<?php

namespace App\Support\Exports\Render;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Right-to-left, the way PHPWord says to do it.
 *
 * EVERY PARAGRAPH CAN BE RIGHT-ALIGNED AND THE DOCUMENT STILL READ LEFT-TO-RIGHT.
 * That is not a contradiction, it is `w:jc`. The values are not all the same kind:
 * `left` and `right` are ABSOLUTE — physical edges of the page — while `start` and
 * `end` are LOGICAL — the beginning and the end of the line in reading order. Inside a
 * paragraph marked `w:bidi`, Word resolves the absolute pair against the paragraph's
 * own direction, so `right` lands on the edge that is left on the page. A document
 * whose every paragraph says `w:jc="right"` and `w:bidi` is a left-aligned document,
 * and it is correct in every particular.
 *
 * That is why checking the paragraphs found nothing, twice. The XML said what it was
 * told to say and meant something else.
 *
 * PHPWord's own answer, from its own sources:
 *
 *   samples/Sample_36_RTL.php   `alignment => SimpleType\Jc::END`, `['rtl' => true]`
 *   samples/Sample_43_RTLDefault.php   `Settings::setDefaultRtl(true)`
 *   Writer/HTML/Style/Paragraph.php     `default: // all others, including Jc::START
 *                                            $textAlign = $style->isBidi() ? 'right' : 'left';`
 *
 * The HTML writer is the proof and worth reading twice: `START` in a bidi paragraph
 * IS right. So is every other logical value. The absolute pair is the trap.
 *
 * `Jc::START` is used rather than `Jc::END` because START is what PHPWord itself
 * substitutes for a bidi paragraph that declares no alignment, and because a document
 * that may one day contain a left-to-right passage wants START rather than a
 * hard-coded edge.
 */
final class DocxRtl
{
    /**
     * Run the callback with PHPWord's own RTL default in force.
     *
     * `Settings::setDefaultRtl` is a process-wide static, so it is set, used, and
     * put back — a library that leaks a global into whatever renders next is a
     * library nobody can reason about. It is also redundant for everything written
     * here, because each element says so explicitly, and is applied anyway so that
     * anything PHPWord adds on our behalf later inherits the right direction instead
     * of the wrong one.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function with(callable $callback): mixed
    {
        $previous = Settings::isDefaultRtl();

        Settings::setDefaultRtl(true);

        try {
            return $callback();
        } finally {
            Settings::setDefaultRtl($previous);
        }
    }

    /**
     * The run style for text that lives inside an RTL paragraph.
     *
     * No font name. On a template that is decided, and on a document from scratch it
     * is the default the document declares — either way, naming Tahoma over a letterhead
     * set in B Nazanin does not produce B Nazanin, it produces a document in two
     * typefaces.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function run(array $extra = [], ?string $font = null): array
    {
        // `rtl` is not a preference. Without it Word shapes Persian left to right and
        // the letters come out in the wrong order.
        $style = [...$extra, 'rtl' => true];

        return $font === null ? $style : [...$style, 'name' => $font];
    }

    /**
     * The paragraph style for an RTL paragraph.
     *
     * `bidi` marks the paragraph's direction; the alignment is LOGICAL so it means the
     * same thing in either direction.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function paragraph(array $extra = []): array
    {
        return [...$extra, 'bidi' => true, 'alignment' => Jc::START];
    }

    /**
     * A table that reads right to left, cell order included.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function table(array $extra = []): array
    {
        return [...$extra, 'bidiVisual' => true];
    }

    /**
     * The style the meeting is composed with, given who is underneath.
     *
     * A document of our own and a letterhead somebody else made are different
     * questions: the first needs a font named, the second must not have one imposed.
     */
    public function bodyStyle(): DocxBodyStyle
    {
        return DocxBodyStyle::Inherited;
    }
}
