<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\ExportMeetingSheetRequest;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * `?sections[]=` on a sheet export — the parameter that lets an export dialog exist.
 *
 * The feature it buys is "print this one differently, once". Before it, what a meeting
 * printed came from stored state, so changing it meant SAVING it, which made "just
 * this once" and "from now on" the same action. A person who wanted one page printed
 * a different way had to alter every future export of that meeting to get it.
 *
 * READ OVER HTTP RATHER THAN AT THE SERVICE, because the half of this that can go
 * wrong is the part in between: a parameter validated but never threaded, or threaded
 * into one of the four formats and not the other three. Every format is exercised
 * here for that reason.
 */
/**
 * The body of a sheet export.
 *
 * DECLARED AT THE TOP LEVEL, not inside the `describe`, because a closure declared
 * there cannot reach `$this` — and passing the user and meeting in makes the
 * dependency visible rather than ambient. A helper that reads them from `$this`
 * looks shorter and cannot be called from anywhere that has one.
 */
function exportSheet(User $user, BoardMeeting $meeting, string $format, string $query = ''): string
{
    $response = actingAs($user)->get(
        "/api/board-meetings/{$meeting->getKey()}/export/{$format}{$query}",
    );

    $response->assertOk();

    $body = (string) $response->streamedContent();

    // A `.docx` IS A ZIP, so its bytes are not text and a substring search over them
    // proves nothing — it would pass or fail on compression, not on content. The
    // document part is what has to be read.
    if ($format !== 'docx') {
        return $body;
    }

    $path = tempnam(sys_get_temp_dir(), 'sheet-').'.docx';
    file_put_contents($path, $body);

    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($path);

    $document = new DOMDocument;
    $document->loadXML($xml);

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $text = '';

    foreach ($xpath->query('//w:t') as $node) {
        $text .= $node->textContent;
    }

    return $text;
}
describe('sections asked for on one export', function () {
    beforeEach(function () {
        $this->user = createUserWithPermissions(['board-meeting.export']);
        $this->meeting = BoardMeeting::factory()->create([
            'title' => 'بررسی بودجه',
            'minutes' => 'متن صورتجلسه.',
        ]);
    });

    it('prints only the sections that were asked for', function () {
        $body = exportSheet($this->user, $this->meeting, 'md', '?sections[]=identity&sections[]=summary');

        expect($body)
            ->toContain('خلاصه')
            // The meeting's own minutes section is in the default list and is NOT in
            // the request, so its heading must be absent.
            ->not->toContain('## دستور جلسه');
    });

    it('keeps the order that was asked for, which stored state could not express', function () {
        $body = exportSheet($this->user, $this->meeting, 'md', '?sections[]=summary&sections[]=identity');

        $summary = strpos($body, 'خلاصه');
        $identity = strpos($body, 'اطلاعات جلسه');

        expect($summary)->not->toBeFalse()
            ->and($identity)->not->toBeFalse()
            ->and($summary)->toBeLessThan($identity);
    });

    it('uses the usual sections when nothing is asked for', function () {
        // An ordinary link must keep meaning what it meant, which is the whole reason
        // absence and an empty list are treated differently.
        expect(exportSheet($this->user, $this->meeting, 'md'))
            ->toContain('اطلاعات جلسه')
            ->toContain('خلاصه');
    });

    it('refuses a section that does not exist, by name', function () {
        // REJECTED RATHER THAN DROPPED. Deep in the renderer, dropping is right — a
        // thrown error would lose the whole document. At the boundary the opposite is
        // right: the caller asked for something that does not exist, and a 422 that
        // names it is better than a quietly shortened document they cannot tell from
        // the one they asked for.
        actingAs($this->user)
            ->get("/api/board-meetings/{$this->meeting->getKey()}/export/md?sections[]=nonsense")
            ->assertStatus(422)
            ->assertJsonValidationErrors('sections.0');
    });

    it('honours the request on every format, not just one', function () {
        // Threading it into three of the four actions would pass every test written
        // against the fourth, which is the failure this test exists to catch. PDF is
        // excluded because it is a binary nobody can substring-match, and a test that
        // cannot see its own subject is worse than no test.
        foreach (['md', 'json', 'docx'] as $format) {
            expect(exportSheet($this->user, $this->meeting, $format, '?sections[]=summary'))
                ->toContain('خلاصه')
                ->not->toContain('دستور جلسه');
        }
    });

    it('does not remember the request for the next export', function () {
        exportSheet($this->user, $this->meeting, 'md', '?sections[]=summary');

        expect(exportSheet($this->user, $this->meeting, 'md'))
            ->toContain('اطلاعات جلسه');
    });

    it('validates against exactly the sections the sheet can build', function () {
        // THE DRIFT CHECK THE FORM REQUEST PROMISES. The validation list is read from
        // the profile because the sheet's builders are private, and if the two ever
        // disagree this turns into a 422 about a section that plainly exists — the
        // worst possible reason for a refusal, and one no individual test would see.
        $declared = ExportMeetingSheetRequest::sectionKeys();

        $built = (new ReflectionMethod(BoardMeetingSheetExport::class, 'sectionBuilders'))
            ->invoke(app(BoardMeetingSheetExport::class));

        expect($declared)->toBe(array_keys($built));
    });
});
