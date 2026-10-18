<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Settings\Exceptions\InvalidSetting;
use App\Support\Exports\Exceptions\InvalidFileTemplate;
use App\Support\Exports\Templates\FileTemplateInspector;
use App\Support\Exports\Templates\FileTemplateLibrary;
use App\Support\Exports\Templates\FileTemplateService;
use App\Support\Exports\Templates\FileTemplateStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

/**
 * Uploading a Word template.
 *
 * The refusal cases are the point. A `.docx` is a zip of XML, so "it has the
 * right extension" says nothing about it, and this application later OPENS and
 * rewrites whatever is stored — an unexamined upload turns every export into a
 * re-run of what the person uploaded.
 */
describe('inspecting an uploaded word template', function () {
    /** A real docx, optionally carrying tokens. */
    $docx = function (?string $text = null): string {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text ?? 'یک سند خالی');

        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    };

    it('accepts a plain document and reports its size and kind', function () use ($docx) {
        $path = $docx('فقط متن');

        $result = app(FileTemplateInspector::class)
            ->inspect(new UploadedFile($path, 'plain.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));

        // A file mentioning nothing is a frame with the meeting appended. Reported
        // rather than assumed, because this is the only moment a person can find
        // out — the export is where it becomes visible, and by then it is a
        // document somebody has to open.
        expect($result)->toBe([
            'size' => strlen((string) file_get_contents($path)),
            'kind' => 'frame',
            'placeholders' => [],
        ]);

        @unlink($path);
    });

    it('reports a document holding placeholders as a document, and says which', function () use ($docx) {
        // The inspector used to report WHETHER the file held a placeholder, and
        // that one fact decided whether the template replaced the generated
        // document or merely framed it — so a letterhead with {{date}} in its
        // footer was read as a replacement and the meeting was never added to it.
        //
        // What it reports now is the KIND, decided by `{{content}}` alone, plus the
        // names themselves so a person can see what the file asks for before
        // finding out from an export.
        $path = $docx('جلسه {{title}}');

        $result = app(FileTemplateInspector::class)
            ->inspect(new UploadedFile($path, 'filled.docx', null, null, true));

        expect($result)->toMatchArray([
            'kind' => 'detail',
            'placeholders' => ['title'],
        ]);

        @unlink($path);
    });

    it('reads a document marker Word split across runs as a frame', function () use ($docx) {
        // THE SAME BUG, ONE LAYER DOWN. A `{{content}}` that Word broke into
        // `{{cont`, `ent`, `}}` is still one marker to whoever wrote the template.
        // Counted from the raw XML it looks like a file holding no document
        // marker at all, so a frame is read as a document somebody wrote by hand
        // — and at export the meeting is then never generated and the file comes
        // out nearly empty, with nothing reporting it. The letterhead failure,
        // reproduced by the splitting rather than by the marker.
        //
        // Hand-built because PHPWord will not write a split macro on request, and
        // the split is the entire thing being tested.
        //
        // The three separate `<w:r>` runs are what Word writes and what PHPWord
        // merges back into one `TextRun` holding three `Text` children on load —
        // so this is the shape the fold has to cope with, rather than a single run
        // holding three `<w:t>`, which is a shape Word does not produce.
        $path = $docx('{{content}}');

        $zip = new ZipArchive;
        $zip->open($path);

        $xml = (string) $zip->getFromName('word/document.xml');

        $split = str_replace(
            '<w:r><w:rPr/><w:t xml:space="preserve">{{content}}</w:t></w:r>',
            '<w:r><w:rPr/><w:t>{{cont</w:t></w:r>'
                .'<w:r><w:rPr/><w:t>ent</w:t></w:r>'
                .'<w:r><w:rPr/><w:t>}}</w:t></w:r>',
            $xml,
        );

        // If the replacement did not land, the assertion below would be testing a
        // document that was never broken and would pass for the wrong reason.
        expect($split)->not->toBe($xml);

        $zip->addFromString('word/document.xml', $split);
        $zip->close();

        $result = app(FileTemplateInspector::class)
            ->inspect(new UploadedFile($path, 'split.docx', null, null, true));

        expect($result)->toMatchArray([
            'kind' => 'frame',
            'placeholders' => ['content'],
        ]);

        @unlink($path);
    });

    it('refuses a file that is not a zip at all', function () {
        $path = tempnam(sys_get_temp_dir(), 'notzip-');
        file_put_contents($path, 'this is plain text wearing a docx extension');

        expect(fn () => app(FileTemplateInspector::class)->inspect(new UploadedFile($path, 'fake.docx')))
            ->toThrow(InvalidFileTemplate::class);

        @unlink($path);
    });

    it('refuses a document carrying macros', function () use ($docx) {
        // A renamed .docm is a .docx by extension and a macro container by
        // content. Nothing here needs one, so the check is not configurable.
        $source = $docx('سند');

        $withMacro = tempnam(sys_get_temp_dir(), 'macro-').'.docx';
        copy($source, $withMacro);

        $zip = new ZipArchive;
        $zip->open($withMacro);
        $zip->addFromString('word/vbaProject.bin', 'not really a macro, but the part is what matters');
        $zip->close();

        expect(fn () => app(FileTemplateInspector::class)->inspect(new UploadedFile($withMacro, 'macro.docx')))
            ->toThrow(InvalidFileTemplate::class);

        @unlink($source);
        @unlink($withMacro);
    });

    it('refuses a zip with no document part', function () {
        $path = tempnam(sys_get_temp_dir(), 'empty-').'.docx';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'not a document');
        $zip->close();

        // Storing this happily and failing at export time — inside a Word
        // library, with an error nobody can act on — is what this prevents.
        expect(fn () => app(FileTemplateInspector::class)->inspect(new UploadedFile($path, 'empty.docx')))
            ->toThrow(InvalidFileTemplate::class);

        @unlink($path);
    });

    it('refuses a file past the size cap', function () use ($docx) {
        $path = $docx(str_repeat('متن بلند ', 200));

        config(['exports.word.max_bytes' => 32]);

        expect(fn () => app(FileTemplateInspector::class)->inspect(new UploadedFile($path, 'big.docx')))
            ->toThrow(InvalidFileTemplate::class);

        @unlink($path);
    });

    it('accepts a template whose only placeholder is in a header, and fills it', function () {
        // This used to assert that the inspector FOUND the placeholder, because
        // that decided whether the file replaced the generated document. It does
        // not decide anything now — which is the point, since a letterhead with
        // {{date}} in its header was read as a replacement and exported with no
        // meeting on it. The claim worth keeping is the outcome: the file is
        // accepted, and the header is filled like any other placeholder.
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('متن');

        // `addHeader` rather than a Header in the section options: the section
        // has to own the header for the part to be written into the archive at
        // all, and passing one in options makes PHPWord build a Footer too.
        $section->addHeader()->addText('{{title}}');

        $path = tempnam(sys_get_temp_dir(), 'hdr-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $template = app(FileTemplateLibrary::class)->upload(
            new UploadedFile($path, 'hdr.docx', null, null, true),
            'docx',
            'boardmeeting',
            'سربرگ',
        );

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $file = app(FileTemplateService::class)->render(
            app(BoardMeetingSheetExport::class),
            $meeting,
            new BoardMeetingTemplateFields,
            $template,
        );

        $out = tempnam(sys_get_temp_dir(), 'hdr-out-').'.docx';
        rewind($file->stream);
        file_put_contents($out, stream_get_contents($file->stream));

        $zip = new ZipArchive;
        $zip->open($out);
        $header = (string) $zip->getFromName('word/header1.xml');
        $zip->close();

        expect($header)->toContain('بودجه')
            ->and($header)->not->toContain('{{title}}');

        @unlink($path);
        @unlink($out);
    });
});

describe('the word template library', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->library = app(FileTemplateLibrary::class);
        $this->store = app(FileTemplateStore::class);
    });

    /** A real docx upload with a given file name and optional body text. */
    $upload = function (string $filename, ?string $text = 'متن') {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text ?? 'متن');

        $path = tempnam(sys_get_temp_dir(), 'up-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return new UploadedFile($path, $filename, null, null, true);
    };

    it('stores an uploaded file with a row that points at it', function () use ($upload) {
        $template = $this->library->upload(
            $upload('official-letter.docx', '{{title}}'),
            format: 'docx',
            domain: 'boardmeeting',
            name: 'نامهٔ رسمی',
        );

        expect($template->key())->toBe('template/docx/boardmeeting/official-letter')
            ->and($this->store->has($template))->toBeTrue()
            ->and($this->library->find('docx', 'boardmeeting', 'official-letter'))->not->toBeNull();
    });

    it('keeps a Persian name out of the key', function () use ($upload) {
        $template = $this->library->upload(
            $upload('nama.docx'),
            format: 'docx',
            domain: 'boardmeeting',
            name: 'نامهٔ رسمی جلسه',
        );

        expect($template->name())->toBe('نامهٔ رسمی جلسه')
            ->and($template->key())->toBe('template/docx/boardmeeting/nama');
    });

    it('suffixes a second file of the same name rather than refusing it', function () use ($upload) {
        // Two people uploading "official.docx" is ordinary; being told to rename
        // the file is not help.
        $first = $this->library->upload($upload('official.docx'), 'docx', 'boardmeeting', 'اول');
        $second = $this->library->upload($upload('official.docx'), 'docx', 'boardmeeting', 'دوم');

        expect($first->slug())->toBe('official')
            ->and($second->slug())->toBe('official-2')
            ->and($this->library->forFormatDomain('docx', 'boardmeeting'))->toHaveCount(2);
    });

    it('falls back to a handle when the file name has nothing usable in it', function () use ($upload) {
        $template = $this->library->upload($upload('نامه رسمی.docx'), 'docx', 'boardmeeting', 'نامه');

        // Inventing a transliteration would put a machine-made slug in a key
        // somebody may read in a log.
        expect($template->slug())->toStartWith('tpl-');
    });

    it('writes no row when the file cannot be accepted', function () use ($upload) {
        config(['exports.word.max_bytes' => 8]);

        try {
            $this->library->upload($upload('too-big.docx'), 'docx', 'boardmeeting', 'بزرگ');
        } catch (InvalidFileTemplate) {
            // Expected.
        }

        // A row pointing at a template that cannot be exported would offer itself
        // in the picker and fail on use; that failure has to happen at upload.
        expect($this->library->forFormatDomain('docx', 'boardmeeting'))->toBeEmpty();
    });

    it('has one default per scope, and promoting another moves it', function () use ($upload) {
        $first = $this->library->upload($upload('a.docx'), 'docx', 'boardmeeting', 'الف', isDefault: true);
        $second = $this->library->upload($upload('b.docx'), 'docx', 'boardmeeting', 'ب');

        expect($this->library->defaultFor('docx', 'boardmeeting')?->slug())->toBe('a');

        $this->library->promoteDefault($second);

        expect($this->library->defaultFor('docx', 'boardmeeting')?->slug())->toBe('b')
            ->and($this->library->find('docx', 'boardmeeting', 'a')?->isDefault())->toBeFalse();
    });

    it('keeps an entity-scoped template out of the domain list', function () use ($upload) {
        $this->library->upload($upload('domain.docx'), 'docx', 'boardmeeting', 'دامنه');
        $this->library->upload($upload('meeting.docx'), 'docx', 'boardmeeting', 'این جلسه', entity: 'session/42');

        expect($this->library->forFormatDomain('docx', 'boardmeeting'))->toHaveCount(1)
            ->and($this->library->forFormatDomain('docx', 'boardmeeting', 'session/42'))->toHaveCount(1);
    });

    it('gives the two scopes separate defaults', function () use ($upload) {
        $this->library->upload($upload('d.docx'), 'docx', 'boardmeeting', 'دامنه', isDefault: true);
        $this->library->upload(
            $upload('m.docx'),
            'docx',
            'boardmeeting',
            'جلسه',
            entity: 'session/42',
            isDefault: true,
        );

        // One meeting having its own default must not silently take the domain's.
        expect($this->library->defaultFor('docx', 'boardmeeting')?->slug())->toBe('d')
            ->and($this->library->defaultFor('docx', 'boardmeeting', 'session/42')?->slug())->toBe('m');
    });

    it('removes the file as well as the row', function () use ($upload) {
        $template = $this->library->upload($upload('gone.docx'), 'docx', 'boardmeeting', 'موقتی');

        expect($this->store->has($template))->toBeTrue();

        $this->library->delete($template);

        expect($this->store->has($template))->toBeFalse()
            ->and($this->library->find('docx', 'boardmeeting', 'gone'))->toBeNull();
    });

    it('lists the default first', function () use ($upload) {
        $this->library->upload($upload('a.docx'), 'docx', 'boardmeeting', 'الف');
        $this->library->upload($upload('z.docx'), 'docx', 'boardmeeting', 'ز', isDefault: true);

        expect($this->library->forFormatDomain('docx', 'boardmeeting')[0]->slug())->toBe('z');
    });

    it('refuses a template with no name to recognise', function () use ($upload) {
        // A blank name is refused by the VALUE, not by the upload check: it is a
        // field the request validates, and the store's own strictness is what
        // stops a nameless row ever being written.
        expect(fn () => $this->library->upload($upload('x.docx'), 'docx', 'boardmeeting', '   '))
            ->toThrow(InvalidSetting::class);
    });
});
