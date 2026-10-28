<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\DocxTemplateFiller;
use App\Support\Exports\Templates\GeneralValues;
use App\Support\Exports\Templates\JsonTemplateFiller;
use App\Support\Exports\Templates\MarkdownTemplateFiller;
use App\Support\Exports\Templates\TemplateFieldRegistry;
use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * The placeholders every template may use, whatever domain it belongs to.
 *
 * A template field is published by a domain — the kernel never guesses, because only
 * the domain knows what a meeting has. These are the exception, and the claim worth
 * testing is not that they fill but what happens when they CANNOT: a general value
 * with nothing behind it has to leave the macro standing rather than print nothing
 * where a word belongs, because a certificate with no date on it and a certificate
 * with an unresolved macro in it are different problems and only one of them is
 * visible.
 */
describe('the general placeholders', function () {
    it('names exactly the four a template may always use', function () {
        expect(GeneralValues::names())
            ->toBe(['export_date', 'created_date', 'domain_label', 'entity_label']);
    });

    it('reads the export date in jalali, like every other date a person reads', function () {
        $registry = new BoardMeetingTemplateFields;

        $values = app(GeneralValues::class)->resolve(
            BoardMeeting::factory()->create(),
            $registry,
        );

        $expected = PersianDateTime::toJalaliDate(CarbonImmutable::now());

        expect($values['export_date'])->toBe($expected)
            // A Gregorian date here would be a template with two calendars on one
            // page, which is a page nobody trusts.
            ->and($values['export_date'])->toMatch('/^\d{4}\/\d{2}\/\d{2}$/');
    });

    it('reads the record creation date when the record has one', function () {
        $meeting = BoardMeeting::factory()->create();

        $values = app(GeneralValues::class)->resolve($meeting, new BoardMeetingTemplateFields);

        expect($values)->toHaveKey('created_date')
            ->and($values['created_date'])->toMatch('/^\d{4}\/\d{2}\/\d{2}$/');
    });

    it('leaves the labels out entirely for a domain that publishes none', function () {
        // NOT an empty string, and NOT the slug. A slug printed in a document is worse
        // than a macro an author can see and fix.
        $values = app(GeneralValues::class)->resolve(
            BoardMeeting::factory()->create(),
            new class implements TemplateFieldRegistry
            {
                public function domain(): string
                {
                    return 'anonymous';
                }

                public function fields(): array
                {
                    return [];
                }

                public function values(object $entity): array
                {
                    return [];
                }
            },
        );

        expect($values)->toHaveKey('export_date')
            ->and($values)->not->toHaveKey('domain_label')
            ->and($values)->not->toHaveKey('entity_label');
    });

    it('leaves the creation date out for a record that cannot say when it was made', function () {
        // Asked of the entity rather than assumed. A value that reached for a property
        // every model happens to have would break on the first entity that is not an
        // Eloquent model — by throwing, in the middle of an export.
        $values = app(GeneralValues::class)->resolve(
            new class
            {
                public string $title = 'x';
            },
            new BoardMeetingTemplateFields,
        );

        expect($values)->not->toHaveKey('created_date');
    });
});

/**
 * The same four through each format, because a general placeholder that works in one
 * and not another is the exact failure the port was drawn to prevent.
 */
describe('the general placeholders in a template', function () {
    /** A meeting whose creation date is fixed, so the assertion can name it. */
    $meeting = function (): BoardMeeting {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه', 'sequence' => 3]);
        $meeting->forceFill(['created_at' => '2024-03-05 09:00:00'])->save();

        return $meeting->refresh();
    };

    $markdown = function (string $template, BoardMeeting $meeting, ?TemplateFieldRegistry $registry = null): string {
        $path = tempnam(sys_get_temp_dir(), 'gen-md-').'.md';
        file_put_contents($path, $template);

        app(MarkdownTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            $registry ?? new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $text = (string) stream_get_contents($stream);
        fclose($stream);

        @unlink($path);

        return $text;
    };

    it('fills them in markdown', function () use ($markdown, $meeting) {
        $text = $markdown(
            '{{domain_label}} / {{entity_label}} / {{created_date}} / {{export_date}}',
            $meeting(),
        );

        expect($text)->toContain('صورتجلسهٔ هیئت مدیره')
            ->and($text)->toContain('1402/12/15')
            ->and($text)->toMatch('/\d{4}\/\d{2}\/\d{2}/')
            ->and($text)->not->toContain('{{');
    });

    it('fills them in json, escaped like any other string value', function () use ($meeting) {
        $meeting = $meeting();

        $path = tempnam(sys_get_temp_dir(), 'gen-json-').'.json';
        file_put_contents($path, '{"kind": "{{domain_label}}", "when": "{{export_date}}"}');

        app(JsonTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $text = (string) stream_get_contents($stream);
        fclose($stream);

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['kind'])
            ->toBe('صورتجلسهٔ هیئت مدیره');

        @unlink($path);
    });

    it('fills them in a word header and footer, not only the body', function () use ($meeting) {
        // The reason decision 8 exists. A page footer carrying the export date is the
        // single most common thing a letterhead template does, and it is in a part of
        // the document the body pass never looks at.
        $meeting = $meeting();

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('بدنه');

        $header = $section->addHeader();
        $header->addText('سربرگ {{domain_label}}');

        $footer = $section->addFooter();
        $footer->addText('تاریخ {{export_date}}');

        $path = tempnam(sys_get_temp_dir(), 'gen-docx-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        app(DocxTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'gen-docx-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));
        fclose($stream);

        $zip = new ZipArchive;
        $zip->open($out);

        $header = (string) $zip->getFromName('word/header1.xml');
        $footer = (string) $zip->getFromName('word/footer1.xml');

        $zip->close();

        expect($header)->toContain('صورتجلسهٔ هیئت مدیره')
            ->and($header)->not->toContain('{{')
            ->and($footer)->toMatch('/\d{4}\/\d{2}\/\d{2}/')
            ->and($footer)->not->toContain('{{');

        @unlink($path);
        @unlink($out);
    });

    it('leaves a general placeholder alone when the domain publishes nothing', function () use ($markdown) {
        // Not filled with nothing. A certificate that says nothing where the domain's
        // name belongs is finished-looking and wrong, and nobody can tell it apart
        // from one with no name on it.
        $text = $markdown(
            '{{domain_label}}',
            BoardMeeting::factory()->create(),
            new class implements TemplateFieldRegistry
            {
                public function domain(): string
                {
                    return 'anonymous';
                }

                public function fields(): array
                {
                    return [];
                }

                public function values(object $entity): array
                {
                    return [];
                }
            },
        );

        expect($text)->toBe('{{domain_label}}');
    });
});

/**
 * Discoverability, which is half of whether a placeholder exists.
 *
 * A placeholder nobody is shown is a placeholder nobody uses, and this one was
 * nearly that: it filled correctly in all three formats while the cheat sheet the
 * upload screen renders listed only the domain's own fields. The same lesson as
 * reporting a template's kind at upload — a rule applied silently is a rule that
 * gets rediscovered as a bug.
 */
describe('the general placeholders in the cheat sheet', function () {
    it('lists all four, each with a label of its own', function () {
        $this->actingAs(createUserWithPermissions(['export-template.view']));

        $response = $this->getJson('/api/templates/fields');

        $response->assertOk();

        $general = $response->json('data.general');

        expect(array_column($general, 'key'))
            ->toBe(['export_date', 'created_date', 'domain_label', 'entity_label']);

        foreach ($general as $entry) {
            expect($entry['label'])->toBeString()->not->toBe($entry['key']);
        }
    });
});
