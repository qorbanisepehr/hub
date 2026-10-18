<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\TemplateFiller;
use App\Support\Exports\Templates\WordTemplateLibrary;
use App\Support\Exports\Templates\WordTemplateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * A template's header and footer come out intact, and are filled like the body.
 *
 * Every mechanism by which they could plausibly be lost was checked against a
 * real .docx and every one of them survived: the parts are still in the archive,
 * the section still references them by the same relationship ids, and a page
 * number is not flattened to text. Composition writes the document back out and
 * the placeholder pass rewrites it again, so a header now has to survive two
 * round trips rather than one.
 *
 * This is the one that matters most: a header can be present in the archive and
 * unreachable from the section, and Word would show nothing at all — which the
 * reader cannot tell apart from the header having been dropped.
 *
 * THE FAILURE THIS FILE EXISTS FOR. Templates used to be classified at upload by
 * scanning the body, the headers AND the footers for a placeholder, and a file
 * with `{{date}}` in its footer was therefore read as one that REPLACES the
 * generated document. The meeting was never built for it, so the export was a
 * letterhead with no minutes on it — and nothing anywhere said so. There is one
 * path now, and a footer placeholder is filled like any other.
 */
describe('a template keeps its header and footer', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->library = app(WordTemplateLibrary::class);
    });

    $xml = function (string $path, string $part): string {
        $zip = new ZipArchive;
        $zip->open($path);
        $found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ((string) $zip->getNameIndex($i) === $part) {
                $found = true;
            }
        }
        $xml = $found ? (string) $zip->getFromName($part) : '';
        $zip->close();

        return $xml;
    };

    /** The header/footer relationships the section declares, in order. */
    $references = function (string $documentXml): array {
        preg_match_all(
            '/<w:(headerReference|footerReference)[^>]*w:type="([^"]+)"[^>]*r:id="([^"]+)"/',
            $documentXml,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(fn (array $m): string => $m[1].':'.$m[2].'='.$m[3], $matches);
    };

    $save = function (PhpWord $phpWord): string {
        $path = tempnam(sys_get_temp_dir(), 'hf-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    };

    /** Fill a template file with a meeting and return the path of the result. */
    $fill = function (string $source, BoardMeeting $meeting): string {
        app(TemplateFiller::class)->render(
            $source,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'hf-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));

        return $out;
    };

    it('exports with the header and footer untouched and still referenced', function () use ($xml, $references, $save) {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addHeader()->addText('سربرگ هیئت مدیره');
        $section->addFooter()->addText('پاورقی');
        $section->addText('متنِ بدنه');

        $source = $save($phpWord);
        $before = ($references)($xml($source, 'word/document.xml'));

        $template = $this->library->upload(
            new UploadedFile($source, 'house.docx', null, null, true),
            domain: 'boardmeeting',
            name: 'سبک خانه',
            isDefault: true,
        );

        $file = app(WordTemplateService::class)->render(
            app(BoardMeetingSheetExport::class),
            BoardMeeting::factory()->create(),
            new BoardMeetingTemplateFields,
            $template,
        );

        $out = tempnam(sys_get_temp_dir(), 'hf-out-').'.docx';
        rewind($file->stream);
        file_put_contents($out, stream_get_contents($file->stream));

        expect(($references)($xml($out, 'word/document.xml')))->toBe($before)
            // The relationship ids are what make the parts reachable; matching
            // them is a stronger claim than merely finding header1.xml present.
            ->and(($xml)($out, 'word/header1.xml'))->toContain('سربرگ')
            ->and(($xml)($out, 'word/footer1.xml'))->toContain('پاورقی');

        @unlink($source);
        @unlink($out);
    });

    it('keeps a static header when the body has placeholders too', function () use ($xml, $save, $fill) {
        // Composition writes the document back out and the fill rewrites it
        // again, so a header has to survive two round trips rather than one.
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addHeader()->addText('سربرگ ایستا');
        $section->addFooter()->addText('پاورقی ایستا');
        $section->addText('عنوان: {{title}}');

        $source = $save($phpWord);
        $out = $fill($source, BoardMeeting::factory()->create(['title' => 'بودجه']));

        expect(($xml)($out, 'word/header1.xml'))->toContain('سربرگ ایستا')
            ->and(($xml)($out, 'word/footer1.xml'))->toContain('پاورقی ایستا')
            ->and(($xml)($out, 'word/document.xml'))->toContain('headerReference');

        @unlink($source);
        @unlink($out);
    });

    it('fills a placeholder that sits in the header or footer', function () use ($xml, $save, $fill) {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addHeader()->addText('صورتجلسهٔ {{title}}');
        $section->addFooter()->addText('شمارهٔ {{sequence}}');
        $section->addText('متنِ بدنه');

        $source = $save($phpWord);
        $out = $fill($source, BoardMeeting::factory()->create(['title' => 'بودجه', 'sequence' => 7]));

        expect(($xml)($out, 'word/header1.xml'))
            ->toContain('صورتجلسهٔ بودجه')
            ->not->toContain('{{title}}');

        // A footer placeholder was the specific thing that used to be skipped:
        // the template was classified by scanning headers, a file with {{date}}
        // in its footer became a "content" template, and the meeting was never
        // added to it at all.
        expect(($xml)($out, 'word/footer1.xml'))
            ->toContain('شمارهٔ 7')
            ->not->toContain('{{sequence}}');

        @unlink($source);
        @unlink($out);
    });
});
