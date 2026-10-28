<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\AdaptiveListMacro;
use App\Support\Exports\Templates\DocxTemplateFiller;
use App\Support\Exports\Templates\MarkdownTemplateFiller;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * THE FIXTURES SPELL AN AMBERSAND AS `&amp;`, AND THAT IS NOT A TYPO.
 *
 * PHPWord's output escaping is OFF by default (`Settings::$outputEscapingEnabled` is
 * false and nothing in this application turns it on), so its writer puts whatever
 * text it is given straight into `<w:t>` with no escaping. A template containing a
 * raw `&` therefore produces a document whose XML does not PARSE — the whole file,
 * not the one run — and `DocxPackage` refuses it with "A Word document could not be
 * read", which says nothing about the cause.
 *
 * Spelling it as `&amp;` here writes valid XML that the DOM reads back as `&`, so
 * the macro still carries a real ampersand and the feature is tested for real.
 *
 * THE EXPORT PATH IS NOT AFFECTED, and that distinction matters: templates come from
 * the author's Word, which escapes its own text, and every value WE substitute goes
 * through `forXml()`. It is only documents this application WRITES with PHPWord that
 * can come out malformed — which is a real defect, and a separate one.
 */

/**
 * `{{@before|name|after|between}}` — a list with each item wrapped.
 *
 * ASKED FOR AS A SYNTAX TO FIX, because a template author kept writing it and it kept
 * printing itself. The shapes it replaces could not produce `@aaa& @bbb&`: an
 * aggregate joins items and nothing else, and a block repeats a region and prints
 * nothing around it.
 *
 * EVERY ITEM IS WRAPPED, NOT THE LIST. That is the whole distinction, and it is what
 * makes the shape worth having: a separator goes BETWEEN items and so cannot mark
 * where one ends, which is what quoting, bracketing and unit-suffixing all need.
 *
 * THE FOURTH PART IS OPTIONAL and defaults to a single space, so the three-part form
 * is the common one and the after-separator's own trailing space usually supplies the
 * gap on its own.
 */
describe('a list with each item wrapped', function () {
    $meeting = function (): BoardMeeting {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $meeting->tags()->createMany([
            ['name' => 'aaa', 'slug' => 'aaa-'.uniqid()],
            ['name' => 'bbb', 'slug' => 'bbb-'.uniqid()],
            ['name' => 'ccc', 'slug' => 'ccc-'.uniqid()],
        ]);

        return $meeting->refresh();
    };

    $word = function (string $template, BoardMeeting $meeting): string {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($template);

        $path = tempnam(sys_get_temp_dir(), 'wrap-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        app(DocxTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $filled = (string) stream_get_contents($stream);
        fclose($stream);
        @unlink($path);

        $out = tempnam(sys_get_temp_dir(), 'wrapo-').'.docx';
        file_put_contents($out, $filled);

        $zip = new ZipArchive;
        $zip->open($out);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($out);

        preg_match_all('#<w:t(?:\s[^>]*)?>([^<]*)</w:t>#', $xml, $m);

        // Decoded, because the values are XML-escaped on the way in and what a
        // reader sees is the decoded text. Reading the raw run would make a correct
        // document look wrong, which is how a test teaches the wrong thing.
        return html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    it('wraps every item, which is the shape itself', function () use ($word, $meeting) {
        // NOT `@aaa& bbb& ccc&`. The before and the after belong to the ITEM, and a
        // separator cannot mark where one item ends — which is the whole reason this
        // shape exists.
        expect($word('{{«|tag.name|» }}', $meeting()))
            ->toContain('«aaa» «bbb» «ccc»');
    });

    it('joins with a single space when there is no fourth part', function () use ($word, $meeting) {
        expect($word('{{|tag.name|، }}', $meeting()))->toContain('aaa، bbb، ccc،');
    });

    it('joins with the fourth part when there is one', function () use ($word, $meeting) {
        expect($word('{{«|tag.name|»، }}', $meeting()))->toContain('«aaa»، «bbb»، «ccc»');
    });

    it('leaves an empty side empty rather than printing a space', function () use ($word, $meeting) {
        expect($word('{{|tag.name|، }}', $meeting()))->toContain('aaa، bbb، ccc،');
    });

    it('prints nothing at all when the list is empty', function () use ($word) {
        // Not `@&` — two marks wrapped around nothing reads as finished punctuation,
        // and a reader of the minutes cannot tell it from real content.
        expect($word('{{«|tag.name|» }}', BoardMeeting::factory()->create()))
            ->not->toContain('«»')
            ->not->toContain('{{');
    });

    it('leaves the whole macro printed when the name is not real', function () use ($word, $meeting) {
        // INCLUDING the marks, for the same reason: a stray `@` next to a visible
        // macro is a clue; a tidy `@` next to nothing is not.
        expect($word('{{«|tag.nmae|» }}', $meeting()))->toContain('{{«|tag.nmae|» }}');
    });

    it('does not mistake a range marker for a wrapped list', function () {
        // A bar IMMEDIATELY after the opening character is what tells them apart.
        // Tested on the parser, because a range only expands when its markers are
        // whole paragraphs — the wrong place to prove which shape a marker is read
        // as.
        expect(AdaptiveListMacro::from('#attendee'))->toBeNull()
            ->and(AdaptiveListMacro::from('/attendee'))->toBeNull()
            ->and(AdaptiveListMacro::from('«|tag.name|» '))->not->toBeNull();
    });

    it('reads adjacent bars as an empty before, not as part of the name', function () {
        // With an empty before the two bars are adjacent. Reading the FIRST as the
        // one closing the name would make the name `& ` and the before `tag.name` —
        // which fills nothing and looks exactly like a working feature.
        $macro = AdaptiveListMacro::from('«|tag.name|» ');

        expect($macro)->not->toBeNull()
            ->and($macro->key)->toBe('tag.name')
            ->and($macro->before)->toBe('«')
            ->and($macro->after)->toBe('» ')
            // The after already trails a space, so nothing is inserted between items:
            // adding a second gap would give @aaa&  @bbb&, which the author would
            // read as a bug in the macro rather than a rule about their own spacing.
            ->and($macro->between)->toBe('');
    });

    it('works the same way in markdown', function () use ($meeting) {
        $m = $meeting();

        $path = tempnam(sys_get_temp_dir(), 'wrapm-').'.md';
        file_put_contents($path, '{{«|tag.name|» }}');

        app(MarkdownTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($m),
            $m,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $text = (string) stream_get_contents($stream);
        fclose($stream);
        @unlink($path);

        expect($text)->toContain('«aaa» «bbb» «ccc»');
    });
});
