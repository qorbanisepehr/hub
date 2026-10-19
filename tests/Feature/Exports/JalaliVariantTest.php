<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\DocxTemplateFiller;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * `{{held_on@jalali}}` — the same date in the other calendar.
 *
 * A VARIANT AND NOT A SECOND NAME, and the reason is that a second name is a thing
 * every domain has to remember to declare. A variant is a thing the kernel offers for
 * any value it can parse, so a domain that forgets still gets every date it already
 * works — only the line of help is missing, not the feature.
 *
 * It is the same `@` convention as `key@list`: a second FORM of one value, not a
 * second value. `held_on` and `held_on@jalali` are one date in two calendars, and
 * the pair reads as what it is.
 *
 * The calendar this reaches for is not a new opinion. `GeneralValues` already dates
 * every export in Jalali, because every other date a person reads in this
 * application is; a template that mixed the two on one page would be a page nobody
 * trusts.
 */
describe('a date in the other calendar', function () {
    /** Fill a template given as text, and return what it printed. */
    $printed = function (string $template): string {
        $meeting = BoardMeeting::factory()->create([
            'title' => 'بودجه',
            'held_on' => '2015-04-29',
        ]);

        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($template);

        $path = tempnam(sys_get_temp_dir(), 'jal-').'.docx';
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

        $out = tempnam(sys_get_temp_dir(), 'jalo-').'.docx';
        file_put_contents($out, $filled);

        $zip = new ZipArchive;
        $zip->open($out);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($out);

        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $text = '';

        foreach ($xpath->query('//w:t') as $node) {
            $text .= $node->textContent;
        }

        return $text;
    };

    it('fills the plain name with the date as the registry words it', function () use ($printed) {
        expect($printed('{{held_on}}'))->toContain('2015/04/29');
    });

    it('fills the variant with the same date in Jalali', function () use ($printed) {
        // Latin digits, because that is what 	oJalaliDate returns and what every
        // other date in a generated document already uses. This variant must not be
        // a second opinion about what a Persian date looks like.
        expect($printed('{{held_on@jalali}}'))->toContain('1394/02/09');
    });

    it('fills both in one template, so the two calendars can be compared', function () use ($printed) {
        // The point of the variant: a document can show one and prove the other
        // without either of them being a differently-named field.
        expect($printed('{{held_on}} / {{held_on@jalali}}'))
            ->toContain('2015/04/29 / 1394/02/09');
    });

    it('leaves the variant printed when the value is not a date', function () use ($printed) {
        // A name that is not a date simply does not answer to `@jalali`, and the
        // macro stays visible. Printing an empty string where a date belongs makes a
        // document that looks finished and says nothing — which nobody reading it can
        // tell apart from one with no date at all.
        expect($printed('{{title@jalali}}'))->toContain('{{title@jalali}}');
    });

    it('says which names are dates, so the cheat sheet can show them', function () {
        // The cheat sheet is read before any record exists, so there is no value to
        // inspect and only the DOMAIN can say which of its names are dates. Without
        // this the variant works and is undiscoverable — which is the same mistake
        // as before, in a new place.
        $dates = array_values(array_map(
            static fn ($field): string => $field->key,
            array_filter((new BoardMeetingTemplateFields)->fields(), static fn ($field): bool => $field->isDate),
        ));

        expect($dates)->toContain('held_on');
    });
});
