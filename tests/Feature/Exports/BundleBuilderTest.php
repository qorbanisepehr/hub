<?php

use App\Support\Exports\BundleBuilder;
use App\Support\Exports\BundleEntry;
use App\Support\Exports\BundleTooLarge;
use App\Support\Exports\MissingBundleSource;
use App\Support\Exports\Value\ExportFile;

/**
 * Files a test created and must clean up. Tracked in one place because the
 * builder's whole contract is about who owns which temporary file, and a test
 * suite that leaks its own would be arguing against itself.
 *
 * @var list<string>
 */
$GLOBALS['bm_bundle_files'] = [];

function bm_bundle_track(string $path): string
{
    $GLOBALS['bm_bundle_files'][] = $path;

    return $path;
}

/** @return list<string> */
function bm_bundle_tracked(): array
{
    return $GLOBALS['bm_bundle_files'];
}

function bm_bundle_forget(): void
{
    $GLOBALS['bm_bundle_files'] = [];
}

/** A real file on disk, so ZipArchive has something true to add. */
function bm_bundle_file(string $contents = 'x'): string
{
    $path = tempnam(sys_get_temp_dir(), 'bundle-src-');
    file_put_contents($path, $contents);

    return bm_bundle_track($path);
}

/** @return list<string> */
function bm_bundle_entries(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);

    $names = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = $zip->getNameIndex($index);
    }

    $zip->close();

    return $names;
}

function bm_bundle_contents(string $path, string $entry): string
{
    $zip = new ZipArchive;
    $zip->open($path);
    $contents = (string) $zip->getFromName($entry);
    $zip->close();

    return $contents;
}

/**
 * The zip half of the export kernel.
 *
 * Worth its own tests because two domains now depend on these guarantees
 * instead of each having its own zip code: that a missing source stops the build
 * rather than thinning the archive, that a rendered export can be bundled at
 * all, and that scratch files do not outlive the archive they were made for.
 */
describe('export bundle builder', function () {
    afterEach(function () {
        foreach (bm_bundle_tracked() as $path) {
            @unlink($path);
        }

        bm_bundle_forget();
    });

    it('packs files under their folder and suffixes a name collision', function () {
        $bundle = (new BundleBuilder)->build([
            BundleEntry::fromFile(bm_bundle_file(), 'minutes.pdf', '1404-06-15'),
            BundleEntry::fromFile(bm_bundle_file(), 'minutes.pdf', '1404-06-15'),
        ], 'bundle.zip');

        expect(bm_bundle_entries($bundle->path))
            ->toBe(['1404-06-15/minutes.pdf', '1404-06-15/minutes-2.pdf']);

        $bundle->delete();
    });

    it('keeps same-named files in different folders apart', function () {
        $bundle = (new BundleBuilder)->build([
            BundleEntry::fromFile(bm_bundle_file(), 'resume.pdf', 'cv-1'),
            BundleEntry::fromFile(bm_bundle_file(), 'resume.pdf', 'cv-2'),
        ], 'bundle.zip');

        expect(bm_bundle_entries($bundle->path))
            ->toBe(['cv-1/resume.pdf', 'cv-2/resume.pdf']);

        $bundle->delete();
    });

    it('bundles a rendered export and cleans up the file it spilled', function () {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '%PDF-1.4 sheet');
        rewind($stream);

        $entry = BundleEntry::fromExport(
            new ExportFile('board-meeting.pdf', 'application/pdf', $stream),
            'board-meeting.pdf',
        );

        $spilled = $entry->sourcePath;
        expect(is_file($spilled))->toBeTrue();

        $bundle = (new BundleBuilder)->build([$entry], 'bundle.zip');

        expect(bm_bundle_contents($bundle->path, 'board-meeting.pdf'))->toBe('%PDF-1.4 sheet');

        // The bytes were spilled to disk only to be zipped once. Leaving them
        // behind would fill /tmp on every single-meeting bundle ever built.
        expect(is_file($spilled))->toBeFalse();

        $bundle->delete();
    });

    it('never leaves a stored source behind', function () {
        $stored = bm_bundle_file();

        $bundle = (new BundleBuilder)->build([
            BundleEntry::fromFile($stored, 'scan.pdf'),
        ], 'bundle.zip');

        expect(is_file($stored))->toBeTrue();

        $bundle->delete();
    });

    it('refuses the whole build when a source is missing', function () {
        $present = bm_bundle_file();

        expect(fn () => (new BundleBuilder)->build([
            BundleEntry::fromFile($present, 'here.pdf'),
            BundleEntry::fromFile($present.'-never-existed', 'gone.pdf'),
        ], 'bundle.zip'))->toThrow(MissingBundleSource::class);
    });

    it('refuses above the count cap the caller chose', function () {
        $file = bm_bundle_file();

        expect(fn () => (new BundleBuilder)->build([
            BundleEntry::fromFile($file, 'a.pdf'),
            BundleEntry::fromFile($file, 'b.pdf'),
        ], 'bundle.zip', maxEntries: 1))->toThrow(BundleTooLarge::class);
    });

    it('refuses above the byte cap the caller chose', function () {
        $file = bm_bundle_file(str_repeat('x', 4096));

        expect(fn () => (new BundleBuilder)->build(
            [BundleEntry::fromFile($file, 'big.pdf')],
            'bundle.zip',
            maxBytes: 1024,
        ))->toThrow(BundleTooLarge::class);
    });

    it('reports how many entries landed, so a caller can log it', function () {
        // Two DISTINCT files: the builder now ships one file once however many
        // entries name it, so pointing both entries at one file would be
        // counting a duplicate rather than counting entries.
        $bundle = (new BundleBuilder)->build([
            BundleEntry::fromFile(bm_bundle_file(), 'a.pdf'),
            BundleEntry::fromFile(bm_bundle_file(), 'b.pdf'),
        ], 'bundle.zip');

        expect($bundle->entryCount)->toBe(2)
            ->and($bundle->mimeType)->toBe('application/zip');

        $bundle->delete();
    });
});
