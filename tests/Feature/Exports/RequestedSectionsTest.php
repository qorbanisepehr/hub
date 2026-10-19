<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingProfile;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;

/**
 * Asking for a different set of sections, for one export, without saving anything.
 *
 * THE UNBLOCK FOR AN EXPORT DIALOG. Sections used to come from stored state, so the
 * only way to change what an export printed was to SAVE the change — which means
 * "just this once" and "from now on" are the same action, and a person who wanted to
 * print one page differently could not do it without changing every future export of
 * that meeting.
 *
 * The parameter is deliberately nullable and additive. Null means "whatever this is
 * usually exported with", so every existing caller is unchanged — and widening an
 * interface method with an optional parameter is a change the four sources compile
 * through rather than a second method only one of them has.
 */
describe('sections asked for on one export', function () {
    /**
     * The headings a document actually printed.
     *
     * HEADINGS AND NOT KEYS, because that is all a `DocumentSection` carries — the key
     * is consumed inside the sheet and never reaches the spec. Comparing against the
     * domain's own labels keeps the test honest about what a reader would see, rather
     * than reaching into the sheet for a value it deliberately does not expose.
     *
     * @return list<string>
     */
    $printed = function (BoardMeetingSheetExport $sheet, BoardMeeting $meeting, ?array $requested): array {
        $document = $sheet->document($meeting, $requested);

        $headings = [];

        foreach ($document->sections as $section) {
            $headings[] = $section->heading;
        }

        return $headings;
    };

    /** The heading the domain gives a section, or null when it gives none. */
    $headingOf = function (string $key): ?string {
        foreach ((new BoardMeetingProfile)->sections() as $section) {
            if ($section->key === $key) {
                return $section->label;
            }
        }

        return null;
    };

    it('prints what the caller asked for, in the order asked', function () use ($printed, $headingOf) {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);
        $sheet = app(BoardMeetingSheetExport::class);

        $names = $printed($sheet, $meeting, ['summary', 'identity']);

        // A subset, and REORDERED — which stored state could never express, because a
        // stored list is a saved preference rather than a one-off.
        expect($names)->toBe([$headingOf('summary'), $headingOf('identity')]);
    });

    it('prints nothing when the caller asks for nothing', function () use ($printed) {
        // `[]` IS a request, and must not be read as "use the default". That confusion
        // is what a nullable parameter has to be careful about, and an empty list is
        // exactly where it bites.
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        expect($printed(app(BoardMeetingSheetExport::class), $meeting, []))->toBe([]);
    });

    it('falls back to the usual sections when nothing is asked for', function () use ($printed) {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $names = $printed(app(BoardMeetingSheetExport::class), $meeting, null);

        expect($names)->not->toBe([])
            ->and($names)->toBe($printed(app(BoardMeetingSheetExport::class), $meeting, null));
    });

    it('ignores a section it cannot build rather than failing the export', function () use ($printed, $headingOf) {
        // A name the sheet has no builder for is a request it cannot honour. Failing
        // the whole export on one bad name would be worse than leaving it out, and a
        // dialog would have no way to tell the difference between the two.
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        expect($printed(app(BoardMeetingSheetExport::class), $meeting, ['identity', 'nonsense']))
            ->toBe([$headingOf('identity')]);
    });

    it('does not remember the request for the next export', function () use ($printed) {
        // THE POINT. Nothing is written, so the following export is the usual one.
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);
        $sheet = app(BoardMeetingSheetExport::class);

        $printed($sheet, $meeting, ['summary']);

        expect($printed($sheet, $meeting, null))->not->toBe(['summary']);
    });
});
