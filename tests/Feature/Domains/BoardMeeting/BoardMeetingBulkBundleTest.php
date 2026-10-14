<?php

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Many meetings as one archive.
 *
 * The interesting questions are not "does it zip" — the kernel already has tests
 * for that — but the three decisions this endpoint makes on the domain's behalf:
 * what "all" means, which permission guards which part, and what happens when
 * the answer is "too much".
 */
describe('bulk board meeting bundle', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->app->setLocale('fa');
    });

    /** Store real bytes for a meeting's approvals, so the archive has files to add. */
    $storeScans = function (BoardMeeting $meeting): void {
        foreach ($meeting->documentUsages as $usage) {
            Storage::disk($usage->document->disk)->put($usage->document->path, 'scanned page');
        }
    };

    it('refuses an unauthenticated caller', function () {
        $this->postJson('/api/board-meetings/export/bundle', ['formats' => ['pdf']])
            ->assertStatus(401);
    });

    it('refuses a format nothing is registered for', function () {
        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['pdf', 'nonsense']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('formats.1');
    });

    it('refuses an empty format list rather than building an empty archive', function () {
        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('formats');
    });

    it('bundles every meeting the list can show when no selection is given', function () {
        BoardMeeting::factory()->create(['sequence' => 1]);
        BoardMeeting::factory()->create(['sequence' => 2]);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['md']])
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');

        $names = bm_bulk_zip_names($response->streamedContent());

        // Two meetings, two folders: an archive of many things needs to say
        // which thing is which, or it is a folder of unidentified files.
        expect($names)
            ->toContain('manifest.md')
            ->toContain('manifest.json')
            ->and(count(array_filter($names, fn (string $n): bool => str_ends_with($n, '-summary.md'))))->toBe(2);
    });

    it('honours the list filters, so "all" means all of what is on screen', function () {
        $inRange = BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2026-01-05'), 1)->create();
        $outside = BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2026-06-05'), 2)->create();

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['md'],
                'date_from' => '2026/01/01',
                'date_to' => '2026/02/01',
            ])
            ->assertStatus(200);

        $manifest = json_decode(
            bm_bulk_zip_read($response->streamedContent(), 'manifest.json'),
            true,
        );

        // Exactly the in-window meeting is listed. Asserted against its own
        // registry label rather than a date string, so the test cannot pass by
        // matching a coincidence in a converted date.
        expect($manifest['sections'])->toHaveCount(1)
            ->and($manifest['sections'][0]['heading'])->toBe($inRange->registryLabel());
    });

    it('bundles only the selected meetings when ids are given', function () {
        $wanted = BoardMeeting::factory()->create(['sequence' => 5]);
        BoardMeeting::factory()->create(['sequence' => 6]);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['md'],
                'ids' => [$wanted->getKey()],
            ])
            ->assertStatus(200);

        $names = bm_bulk_zip_names($response->streamedContent());

        expect(count(array_filter($names, fn (string $n): bool => str_ends_with($n, '-summary.md'))))->toBe(1);
    });

    it('answers 404 rather than quietly bundling fewer than was asked for', function () {
        $real = BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['md'],
                'ids' => [$real->getKey(), 999999],
            ])
            ->assertStatus(404);
    });

    it('refuses a sheets-only bundle for a reader without export', function () {
        $this->actingAs(createUserWithPermissions(['board-meeting.view']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['pdf']])
            ->assertStatus(403);
    });

    it('refuses attachments for a reader who may export but not download documents', function () {
        // The two-part gate. Without `documents` ticked this caller is served
        // happily; ticking it asks for bytes behind another permission, so the
        // whole request is refused rather than answered with a thinner archive.
        BoardMeeting::factory()->withApprovalDocuments(1)->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['pdf']])
            ->assertStatus(200);

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['pdf'],
                'documents' => true,
            ])
            ->assertStatus(403);
    });

    it('carries the scans when both permissions are held', function () use ($storeScans) {
        $meeting = BoardMeeting::factory()->withApprovalDocuments(1)->create();
        ($storeScans)($meeting);

        $response = $this->actingAs(createUserWithPermissions([
            'board-meeting.export',
            'board-meeting.documents.download',
        ]))->postJson('/api/board-meetings/export/bundle', [
            'formats' => ['md'],
            'documents' => true,
            'ids' => [$meeting->getKey()],
        ])->assertStatus(200);

        $names = bm_bulk_zip_names($response->streamedContent());

        // One sheet, one scan, and the two manifest pages. Counted by what an
        // entry IS rather than by extension: the scan's own extension is the
        // uploader's business, not this test's.
        expect($names)->toHaveCount(4)
            ->and(array_values(array_filter(
                $names,
                fn (string $name): bool => ! str_ends_with($name, '-summary.md')
                    && ! str_starts_with($name, 'manifest.'),
            )))->toHaveCount(1);
    });

    it('refuses rather than building an archive past the cap', function () {
        config(['exports.bundle.max_entries' => 2]);

        BoardMeeting::factory()->count(3)->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['md', 'json']])
            ->assertStatus(422)
            ->assertJsonPath('unit', 'entries');
    });

    it('says so when the filters matched nothing', function () {
        BoardMeeting::factory()->create(['sequence' => 1]);

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['md'],
                'date_from' => '2030/01/01',
                'date_to' => '2030/02/01',
            ])
            ->assertStatus(422);
    });

    it('keeps a scan shared by two meetings in the archive once', function () {
        // One document, attached to both selected meetings. The bytes ship
        // once; the manifest still lists it under both, so neither meeting
        // reads as though it had lost its file.
        $first = BoardMeeting::factory()->create(['sequence' => 1]);
        $second = BoardMeeting::factory()->create(['sequence' => 2]);

        $document = Document::factory()->create();
        $shared = Document::find($document->getKey());
        Storage::disk($shared->disk)->put($shared->path, 'one scan, two meetings');

        foreach ([$first, $second] as $meeting) {
            DocumentUsage::create([
                'document_id' => $shared->getKey(),
                'entity_type' => BoardMeeting::class,
                'entity_id' => $meeting->getKey(),
                'section_key' => null,
                'field_key' => null,
                'metadata' => ['registry_number' => '1001/ص/1404'],
            ]);
        }

        $response = $this->actingAs(createUserWithPermissions([
            'board-meeting.export',
            'board-meeting.documents.download',
        ]))->postJson('/api/board-meetings/export/bundle', [
            'formats' => ['md'],
            'documents' => true,
        ])->assertStatus(200);

        $names = bm_bulk_zip_names($response->streamedContent());
        $manifest = json_decode(bm_bulk_zip_read($response->streamedContent(), 'manifest.json'), true);

        // Two meetings, two sheets, ONE copy of the scan, and the two manifest
        // pages. The manifest still has both sections, so neither meeting reads
        // as though it had lost its file.
        expect($names)->toHaveCount(5)
            ->and(array_values(array_filter(
                $names,
                fn (string $name): bool => ! str_ends_with($name, '-summary.md')
                    && ! str_starts_with($name, 'manifest.'),
            )))->toHaveCount(1)
            ->and($manifest['sections'])->toHaveCount(2);
    });
});

/**
 * @return list<string>
 */
function bm_bulk_zip_names(string $bytes): array
{
    $path = bm_bulk_zip_write($bytes);
    $zip = new ZipArchive;
    $zip->open($path);

    $names = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = (string) $zip->getNameIndex($index);
    }

    $zip->close();
    @unlink($path);

    return $names;
}

function bm_bulk_zip_read(string $bytes, string $entry): string
{
    $path = bm_bulk_zip_write($bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $contents = (string) $zip->getFromName($entry);
    $zip->close();
    @unlink($path);

    return $contents;
}

function bm_bulk_zip_write(string $bytes): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'bulk-bundle-');
    file_put_contents($path, $bytes);

    return $path;
}
