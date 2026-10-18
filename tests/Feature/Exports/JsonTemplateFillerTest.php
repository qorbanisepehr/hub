<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Support\Exports\Templates\JsonTemplateFiller;
use App\Support\Exports\Templates\TemplateFillerRegistry;

/**
 * The JSON implementation of the template port.
 *
 * Everything here is about producing a file that PARSES. A JSON template that comes
 * out looking right is worthless — the whole point of the format is that a system on
 * the other end can read it, and a file with an unescaped quote in it or a missing
 * comma between two array elements is not JSON no matter how plausible it looks in
 * a text editor. So most of these decode the result and assert on the decoded
 * structure, which is the only way to know the bytes were valid.
 */
describe('a json template', function () {
    /**
     * Fill a template given as text and return the text that came out.
     *
     * A real file on disk rather than a string, because that is what the port takes
     * and because the part that cannot be faked — writing and reading back a file the
     * caller did not build in memory — is part of what is being tested.
     *
     * Resolved through the container rather than through `$this`, because a closure
     * declared in `describe()` is invoked from `it()`, where there is no test case to
     * inherit from. That trap has bitten this file's siblings three times.
     */
    $filled = function (string $template, BoardMeeting $meeting): string {
        $path = tempnam(sys_get_temp_dir(), 'json-').'.json';
        file_put_contents($path, $template);

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

        @unlink($path);

        return $text;
    };

    /** A meeting with two attendees and a summary. */
    $meeting = function (): BoardMeeting {
        return BoardMeeting::factory()->withAttendees(
            new Attendee('الف', AttendeeRole::Chair, true),
            new Attendee('ب', AttendeeRole::Member, false),
        )->create(['title' => 'بودجه سال ۱۴۰۵']);
    };

    it('puts the generated document in raw, where an object belongs', function () use ($filled, $meeting) {
        // NOT escaped, and that is the asymmetry the format is built on: `{{content}}`
        // IS data, and every other macro is text that happens to sit inside data.
        //
        // Asserted by decoding, because a template that produced a JSON *string*
        // holding the document would also contain the meeting's title — it would
        // just be unusable by anything downstream.
        $text = $filled('{"meeting": {{content}}}', $meeting());

        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        // The document's own title, not the meeting's: the spec is what the renderer
        // produces, and it titles the document. The meeting's title is inside the
        // sections, which is where the assertion below looks for it.
        expect($decoded['meeting'])->toBeArray()
            ->and($decoded['meeting']['title'])->toBe('صورتجلسهٔ هیئت مدیره')
            ->and($text)->toContain('بودجه سال ۱۴۰۵')
            ->and($text)->not->toContain('{{content}}');
    });

    it('escapes a value as json string content, with the quotes the author typed', function () use ($filled, $meeting) {
        // The quotes are the AUTHOR's. A filler that added its own would produce
        // `""بودجه""` the first time somebody wrote the macro without them.
        $text = $filled('{"title": "{{title}}"}', $meeting());

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['title'])->toBe('بودجه سال ۱۴۰۵')
            ->and($text)->not->toContain('{{title}}');
    });

    it('escapes a value that would otherwise break the file', function () use ($filled) {
        // The reason values are escaped at all, and the only test here that cannot be
        // satisfied by a filler that passes text through untouched.
        $meeting = BoardMeeting::factory()->create(['title' => 'او گفت: "بله"\\ و رفت']);

        $text = $filled('{"title": "{{title}}"}', $meeting);

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['title'])
            ->toBe('او گفت: "بله"\\ و رفت');
    });

    it('expands a range into one object per record, with the commas', function () use ($filled, $meeting) {
        // THE REASON A RANGE EXISTS. An array needs a comma between its elements, so
        // copying a line without adding commas yields a file that is not JSON at all
        // — and the commas are the filler's because a comma the author wrote would be
        // a trailing one on the last element, which is what makes JSON invalid.
        $text = $filled(
            '{"attendees": [{{#attendee}}{"name": "{{attendee.name}}", "present": "{{attendee.is_present}}"}{{/attendee}}]}',
            $meeting(),
        );

        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        expect($decoded['attendees'])->toHaveCount(2)
            ->and($decoded['attendees'][0]['name'])->toBe('الف')
            ->and($decoded['attendees'][1]['name'])->toBe('ب');
    });

    it('leaves an empty array where a range had no records', function () use ($filled) {
        // Not the macros: a file with `{{attendee.name}}` in it is not JSON, and does
        // not say why.
        $text = $filled(
            '{"attendees": [{{#attendee}}{"name": "{{attendee.name}}"}{{/attendee}}]}',
            BoardMeeting::factory()->create(),
        );

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['attendees'])->toBe([])
            ->and($text)->not->toContain('{{attendee');
    });

    it('leaves a macro it has no field for exactly as the author typed it', function () use ($filled, $meeting) {
        // A typo has to be visible somewhere, and the file is the only place the
        // author of the template will look.
        $text = $filled('{"nope": "{{titlee}}"}', $meeting());

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR))->toBe(['nope' => '{{titlee}}']);
    });

    it('generates nothing onto a document the author wrote by hand', function () use ($filled, $meeting) {
        // The same distinction Word and Markdown make, for the same reason: this file
        // has no `{{content}}`, so it is a document somebody wrote and the generated
        // document does not belong in it.
        $text = $filled('{"title": "{{title}}"}', $meeting());

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR))
            ->toBe(['title' => 'بودجه سال ۱۴۰۵']);
    });

    it('leaves a group alone when the author used its macros without a range', function () use ($filled, $meeting) {
        // Documented as the one thing this filler does not second-guess. The author
        // wrote `{{attendee.name}}` and expects something; emptying it would leave
        // them with a file that says nothing about what went wrong.
        $text = $filled('{"who": "{{attendee.name}}"}', $meeting());

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR))->toBe(['who' => '{{attendee.name}}']);
    });

    it('does not fill a macro a value happens to contain', function () use ($filled) {
        // The ordering rule, in the one place JSON makes it visible: a title quoted
        // from a resolution can contain `{{`, and whichever pass ran last would be the
        // one to fill it.
        $meeting = BoardMeeting::factory()->create(['title' => '{{content}}']);

        $text = $filled('{"title": "{{title}}"}', $meeting);

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['title'])->toBe('{{content}}');
    });

    it('tolerates the spacing a person actually types', function () use ($filled, $meeting) {
        $text = $filled('{"title": "{{ title }}"}', $meeting());

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['title'])->toBe('بودجه سال ۱۴۰۵');
    });

    it('is registered, so a json template can be uploaded at all', function () {
        // A filler that exists and is not registered is a format the upload form
        // refuses and the export cannot reach.
        expect(app(TemplateFillerRegistry::class)->get('json'))
            ->toBeInstanceOf(JsonTemplateFiller::class);
    });
});
