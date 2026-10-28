<?php

use App\Support\Exports\BundleBuilder;
use App\Support\Exports\BundleEntry;
use App\Support\Exports\BundleManifest;
use App\Support\Exports\BundleRequest;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\Render\JsonRenderer;
use App\Support\Exports\Render\MarkdownRenderer;
use App\Support\Exports\Render\PdfRenderer;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\Value\Document\DocumentTable;
use InvalidArgumentException;

/**
 * Phase 1 of the export plan: the kernel gaps a hub needs before any domain can
 * bundle anything new.
 *
 * Four pieces, and the reason each exists is in its own test rather than here:
 * JSON for the systems on the other end, a manifest so an archive can say what
 * it holds, deduplication so a shared file ships its bytes once, and a request
 * value that makes "which formats, and do the files travel" one question.
 */
describe('json document renderer', function () {
    it('serialises a document losslessly enough to rebuild it', function () {
        $spec = new DocumentSpec(
            title: 'صورتجلسهٔ هیئت مدیره',
            subtitle: '1404/06/15',
            meta: [new DocumentField('تاریخ صدور', '۱۴۰۴/۰۶/۱۵')],
            sections: [
                new DocumentSection(
                    heading: 'حاضران',
                    fields: [new DocumentField('نام', 'زهرا احمدی')],
                    tables: [new DocumentTable('', ['نقش'], [['رئیس']])],
                ),
            ],
        );

        $stream = fopen('php://temp', 'r+');
        (new JsonRenderer)->render($spec, $stream);
        rewind($stream);
        $json = stream_get_contents($stream);

        // Everything a reader on the other end needs to reconstruct the
        // document: headings, labels, values, table shape, order.
        expect(json_decode($json, true))->toMatchArray([
            'version' => 1,
            'title' => 'صورتجلسهٔ هیئت مدیره',
            'subtitle' => '1404/06/15',
            'meta' => [['label' => 'تاریخ صدور', 'value' => '۱۴۰۴/۰۶/۱۵']],
            'sections' => [[
                'heading' => 'حاضران',
                'fields' => [['label' => 'نام', 'value' => 'زهرا احمدی']],
                'tables' => [['caption' => '', 'headers' => ['نقش'], 'rows' => [['رئیس']]]],
            ]],
        ]);
    });

    it('keeps Persian readable rather than escaping it', function () {
        $stream = fopen('php://temp', 'r+');
        (new JsonRenderer)->render(
            new DocumentSpec('خلاصه', sections: [
                new DocumentSection('', fields: [new DocumentField('موضوع', 'بودجه')]),
            ]),
            $stream,
        );
        rewind($stream);

        // \uXXXX escapes are valid JSON but useless to the human who opens the
        // file in an editor, which is exactly who looks at an export.
        expect(stream_get_contents($stream))->toContain('بودجه');
    });

    it('reports the content type and extension a .json download needs', function () {
        $renderer = new JsonRenderer;

        expect($renderer->contentType())->toBe('application/json')
            ->and($renderer->extension())->toBe('json');
    });

    it('is registered, so every domain with a DocumentSource gains it at once', function () {
        // The whole point of putting it in the registry rather than in one
        // domain: CV, employee profile and questionnaire get JSON the day this
        // line exists, without a line of work in any of them.
        expect(app(DocumentRendererRegistry::class)->get('json'))->toBeInstanceOf(JsonRenderer::class);
    });
});

describe('bundle manifest', function () {
    it('lists every item and the files it contributed', function () {
        $spec = BundleManifest::forItems(
            title: 'Archive',
            itemsLabel: 'Meetings',
            filesLabel: 'File',
            items: [
                ['label' => '1404/06/15 — جلسه ۱۲', 'files' => ['s12/meeting-12.pdf', 's12/scan.pdf']],
                ['label' => '1404/06/22 — جلسه ۱۳', 'files' => ['s13/meeting-13.pdf']],
            ],
        );

        expect($spec->title)->toBe('Archive')
            ->and($spec->sections)->toHaveCount(2)
            ->and($spec->sections[0]->heading)->toBe('1404/06/15 — جلسه ۱۲')
            ->and($spec->sections[0]->tables[0]->rows)->toBe([['s12/meeting-12.pdf'], ['s12/scan.pdf']])
            ->and($spec->meta[0]->value)->toBe('2');
    });

    it('renders as both markdown and json from the one spec', function () {
        $spec = BundleManifest::forItems('Archive', 'Meetings', [
            ['label' => 'meeting-12', 'files' => ['meeting-12.pdf']],
        ]);

        $markdown = fopen('php://temp', 'r+');
        (new MarkdownRenderer)->render($spec, $markdown);
        rewind($markdown);

        $json = fopen('php://temp', 'r+');
        (new JsonRenderer)->render($spec, $json);
        rewind($json);

        expect(stream_get_contents($markdown))->toContain('meeting-12.pdf');
        expect(json_decode(stream_get_contents($json), true)['sections'][0]['tables'][0]['rows'])
            ->toBe([['meeting-12.pdf']]);
    });

    it('lists a file under every item that references it', function () {
        // Deduplication removes the duplicate BYTES; the listing is what says
        // what belongs together, and dropping an item's mention of a file would
        // make the archive read as though that item had lost it.
        $spec = BundleManifest::forItems('Archive', 'Meetings', [
            ['label' => 'meeting-12', 'files' => ['shared-scan.pdf']],
            ['label' => 'meeting-13', 'files' => ['shared-scan.pdf']],
        ]);

        expect($spec->sections[0]->tables[0]->rows)->toBe([['shared-scan.pdf']])
            ->and($spec->sections[1]->tables[0]->rows)->toBe([['shared-scan.pdf']]);
    });

    it('shows an item that contributed nothing rather than hiding it', function () {
        $spec = BundleManifest::forItems('Archive', 'Meetings', [
            ['label' => 'meeting-12', 'files' => []],
        ]);

        expect($spec->sections)->toHaveCount(1)
            ->and($spec->sections[0]->tables[0]->rows)->toBe([]);
    });
});

describe('bundle builder deduplication', function () {
    it('ships a file once however many entries point at it', function () use (&$shared) {
        $shared = bm_export_file('the same bytes');

        $bundle = (new BundleBuilder)->build([
            BundleEntry::fromFile($shared, 's12/scan.pdf'),
            BundleEntry::fromFile($shared, 's13/scan.pdf'),
        ], 'archive.zip');

        // One copy of the bytes; the second item simply does not claim a name.
        expect(bm_export_entries($bundle->path))->toBe(['s12/scan.pdf'])
            ->and($bundle->entryCount)->toBe(1);

        $bundle->delete();
    });

    it('does not let a skipped duplicate burn a name the next entry needs', function () use (&$shared) {
        $shared = bm_export_file('bytes');

        $bundle = (new BundleBuilder)->build([
            BundleEntry::fromFile($shared, 's12/scan.pdf'),
            BundleEntry::fromFile($shared, 's13/scan.pdf'),
            // A different file whose natural name is the one the skipped
            // duplicate would have claimed had it been counted.
            BundleEntry::fromFile(bm_export_file('other'), 's13/scan.pdf'),
        ], 'archive.zip');

        // No `-2`: the duplicate never took a name, so the real file lands on
        // the name it actually wants. Suffixing here would mean the archive
        // had a gap where s13/scan.pdf should be.
        expect(bm_export_entries($bundle->path))
            ->toBe(['s12/scan.pdf', 's13/scan.pdf']);

        $bundle->delete();
    });

    it('does not leak a dedupe decision from one archive into the next', function () use (&$shared) {
        // The builder is a shared singleton, so state kept on the instance
        // would let the second archive skip a file the first one added.
        $shared = bm_export_file('bytes');
        $builder = new BundleBuilder;

        $first = $builder->build([BundleEntry::fromFile($shared, 'a.pdf')], 'one.zip');
        $first->delete();

        $second = $builder->build([BundleEntry::fromFile($shared, 'b.pdf')], 'two.zip');

        expect(bm_export_entries($second->path))->toBe(['b.pdf']);

        $second->delete();
    });
});

describe('bundle request', function () {
    beforeEach(function () {
        $this->registry = new DocumentRendererRegistry;
        $this->registry->register('pdf', new PdfRenderer);
        $this->registry->register('md', new MarkdownRenderer);
        $this->registry->register('json', new JsonRenderer);
    });

    it('keeps the formats it was asked for, in order, without duplicates', function () {
        $request = BundleRequest::sheets($this->registry, ['pdf', ' md ', 'pdf']);

        expect($request->formats)->toBe(['pdf', 'md']);
    });

    it('refuses an empty selection rather than building an empty archive', function () {
        expect(fn () => BundleRequest::sheets($this->registry, []))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => BundleRequest::sheets($this->registry, ['  ']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('refuses a format nothing is registered for', function () {
        // Caught here rather than deep in the builder, because "that is not one
        // of ours" is a failure the caller can act on.
        expect(fn () => BundleRequest::sheets($this->registry, ['pdf', 'docx']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('carries whether the attachments travel with the sheets', function () {
        expect(BundleRequest::sheets($this->registry, ['pdf'])->wantsAttachments())->toBeFalse()
            ->and(BundleRequest::sheetsWithAttachments($this->registry, ['pdf'])->wantsAttachments())->toBeTrue();
    });

    it('resolves a requested format and refuses one it was not asked for', function () {
        $request = BundleRequest::sheets($this->registry, ['json']);

        expect($request->renderer($this->registry, 'json'))->toBeInstanceOf(JsonRenderer::class);

        expect(fn () => $request->renderer($this->registry, 'pdf'))
            ->toThrow(InvalidArgumentException::class);
    });
});

/** A real file on disk, so ZipArchive has something true to add. */
function bm_export_file(string $contents = 'x'): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'bundle-src-');
    file_put_contents($path, $contents);
    $GLOBALS['bm_export_files'][] = $path;

    return $path;
}

/** @return list<string> */
function bm_export_entries(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);

    $names = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = (string) $zip->getNameIndex($index);
    }

    $zip->close();

    return $names;
}

beforeEach(function () {
    $GLOBALS['bm_export_files'] = [];
});

afterEach(function () {
    foreach ($GLOBALS['bm_export_files'] ?? [] as $path) {
        @unlink($path);
    }
});
