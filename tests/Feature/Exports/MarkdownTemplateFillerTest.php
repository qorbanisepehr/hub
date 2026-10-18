<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Support\Exports\Templates\MarkdownTemplateFiller;

/**
 * The Markdown implementation of the template port.
 *
 * These are the cases that decide whether the port was drawn correctly. Every
 * one of them is something the Word filler already handles — the same
 * placeholders, the same entity, the same expectations — and if the two produce
 * different answers then the thing they share was not the thing it looked like.
 *
 * The two that genuinely differ are marked: `{{content}}` may sit anywhere here
 * and not in Word, and a repeating group is a LINE here and a ROW there.
 */
describe('a markdown template', function () {
    /**
     * Fill a template given as text and return the text that came out.
     *
     * A real file on disk rather than a string, because that is what the port
     * takes and because the part that cannot be faked — writing and reading back
     * a file the caller did not build in memory — is part of what is being tested.
     *
     * Resolved through the container rather than through `$this`, because a
     * closure declared here is invoked from `it()`, where there is no test case
     * to inherit from. That trap has bitten this file's siblings three times.
     */
    $filled = function (string $template, BoardMeeting $meeting): string {
        $path = tempnam(sys_get_temp_dir(), 'md-').'.md';
        file_put_contents($path, $template);

        app(MarkdownTemplateFiller::class)->fill(
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

    it('substitutes an inline macro with the field value', function () use ($filled) {
        $text = $filled(
            '# جلسه {{title}} در تاریخ {{held_on}}',
            BoardMeeting::factory()->create(['title' => 'بودجهٔ سالانه']),
        );

        expect($text)->toContain('بودجهٔ سالانه')
            ->not->toContain('{{title}}');
    });

    it('tolerates the spacing a person actually types', function () use ($filled) {
        // The registry spells a name one way and the file is not obliged to.
        expect($filled('# {{ title }}', BoardMeeting::factory()->create(['title' => 'بودجه'])))
            ->toContain('بودجه')
            ->not->toContain('{{');
    });

    it('repeats the line carrying a group once per record', function () use ($filled) {
        // A Markdown table row IS a line, which is what replaces Word's table row
        // here. The rows go in with their macros intact and one pass fills every
        // copy — so a column the author used twice is filled in both.
        $template = "حاضران\n\n| نام | نقش |\n|---|---|\n| {{attendee.name}} | {{attendee.role}} |";

        $text = $filled($template, BoardMeeting::factory()->withAttendees(
            new Attendee('الف', AttendeeRole::Chair, true),
            new Attendee('ب', AttendeeRole::Member, false),
            new Attendee('ج', AttendeeRole::Observer, true),
        )->create());

        expect($text)->toContain('| الف |')->toContain('| ب |')->toContain('| ج |')
            ->not->toContain('{{attendee');

        // Three records, three body rows, under a two-line header.
        expect(substr_count($text, '|---|'))->toBe(1)
            ->and(substr_count($text, '| الف |'))->toBe(1);
    });

    it('leaves a group the file never mentions alone', function () use ($filled) {
        // Asked of the FILE rather than of the entity: a template that does not
        // mention attendees is not asking for attendees.
        $meeting = BoardMeeting::factory()->withAttendees(
            new Attendee('الف', AttendeeRole::Chair, true),
        )->create();

        expect($filled('{{title}}', $meeting))->not->toContain('{{attendee');
    });

    it('takes the line out when the entity has no records for the group', function () use ($filled) {
        // The same argument as Word: a table printing `{{tag.name}}` where a
        // meeting simply had no tags reads as a broken template rather than as a
        // meeting with no tags.
        $text = $filled(
            "| برچسب |\n|---|\n| {{tag.name}} |",
            BoardMeeting::factory()->create(),
        );

        expect($text)->toContain('|---|')
            ->not->toContain('{{tag.name}}');
    });

    it('leaves a macro it has no field for exactly as the author typed it', function () use ($filled) {
        // A typo has to be visible somewhere, and the document is the only place
        // the author will look.
        expect($filled('{{attende}} و {{title}}', BoardMeeting::factory()->create(['title' => 'صورتجلسه'])))
            ->toContain('{{attende}}')
            ->toContain('صورتجلسه');
    });

    it('puts the generated document where {{content}} is, wherever that is', function () use ($filled) {
        // NOT POSSIBLE IN WORD, and the reason a text template is worth having
        // rather than a convenience. PHPWord can only append, so its filler
        // carries a documented rule that the marker be last in the body; replacing
        // text does not care where it is.
        $text = $filled(
            "مقدمه\n\n{{content}}\n\nپیوست",
            BoardMeeting::factory()->create(['title' => 'بودجه']),
        );

        $before = strpos($text, 'مقدمه');
        $middle = strpos($text, 'بودجه');
        $after = strpos($text, 'پیوست');

        expect($before)->not->toBeFalse()
            ->and($middle)->not->toBeFalse()
            ->and($after)->not->toBeFalse()
            ->and($before)->toBeLessThan($middle)
            ->and($middle)->toBeLessThan($after)
            ->and($text)->not->toContain('{{content}}');
    });

    it('keeps a value with a pipe in it from splitting the row', function () use ($filled) {
        // The other shape a value can break, and the one {@see MarkdownText}
        // exists for: the renderer and the filler must agree, or a template would
        // produce a differently-broken table from the same values.
        //
        // Escaping is decided by WHERE the macro lands, not by which field it is:
        // this is an inline macro in a table cell, and there is no syntax that
        // means "this one is a cell", so the filler looks at the line.
        $meeting = BoardMeeting::factory()->create(['title' => 'الف | ب']);

        $text = $filled("| عنوان |\n|---|\n| {{title}} |", $meeting);

        expect($text)->toContain('الف &#124; ب')
            ->and(substr_count($text, '|'))->toBe(6);
    });

    it('leaves a pipe alone where it cannot break anything', function () use ($filled) {
        // The complement, and the reason the decision is made per line rather
        // than per field: the same title in a paragraph is prose, and escaping it
        // there would show the reader `&#124;` in the middle of a sentence.
        $meeting = BoardMeeting::factory()->create(['title' => 'الف | ب']);

        expect($filled('عنوان: {{title}}', $meeting))->toContain('عنوان: الف | ب');
    });

    it('flattens a value with a newline in it so it cannot end the line', function () use ($filled) {
        expect($filled('عنوان: {{title}}', BoardMeeting::factory()->create(['title' => "الف\nب"])))
            ->toContain('عنوان: الف ب');
    });

    it('does not fill a macro a value happens to contain', function () use ($filled) {
        // Substitution is ONE pass. Replacing name by name would let a value
        // containing `{{title}}` be picked up by a later replacement, and the
        // order of the loop would decide what the author saw.
        $text = $filled('{{minutes}}', BoardMeeting::factory()->create([
            'minutes' => 'یادداشت {{title}} اینجا',
        ]));

        expect($text)->toContain('یادداشت {{title}} اینجا');
    });
});
