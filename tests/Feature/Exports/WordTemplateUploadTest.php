<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Settings\Exceptions\InvalidSetting;
use App\Support\Exports\Exceptions\InvalidWordTemplate;
use App\Support\Exports\Templates\WordTemplateInspector;
use App\Support\Exports\Templates\WordTemplateLibrary;
use App\Support\Exports\Templates\WordTemplateService;
use App\Support\Exports\Templates\WordTemplateStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

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

    it('accepts a plain document and reports its size', function () use ($docx) {
        $path = $docx('فقط متن');

        $result = app(WordTemplateInspector::class)
            ->inspect(new UploadedFile($path, 'plain.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));

        expect($result)->toBe(['size' => strlen((string) file_get_contents($path))]);

        @unlink($path);
    });

    it('reports the same thing about a document that holds placeholders', function () use ($docx) {
        // The inspector used to report whether the file held a placeholder, and
        // that one fact decided whether the template replaced the generated
        // document or merely framed it — so a letterhead with {{date}} in its
        // footer was read as a replacement and the meeting was never added to it.
        // There is nothing left to distinguish, so there is nothing to report.
        $path = $docx('جلسه {{title}}');

        $result = app(WordTemplateInspector::class)
            ->inspect(new UploadedFile($path, 'filled.docx', null, null, true));

        expect($result)->toBe(['size' => strlen((string) file_get_contents($path))]);

        @unlink($path);
    });

    it('refuses a file that is not a zip at all', function () {
        $path = tempnam(sys_get_temp_dir(), 'notzip-');
        file_put_contents($path, 'this is plain text wearing a docx extension');

        expect(fn () => app(WordTemplateInspector::class)->inspect(new UploadedFile($path, 'fake.docx')))
            ->toThrow(InvalidWordTemplate::class);

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

        expect(fn () => app(WordTemplateInspector::class)->inspect(new UploadedFile($withMacro, 'macro.docx')))
            ->toThrow(InvalidWordTemplate::class);

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
        expect(fn () => app(WordTemplateInspector::class)->inspect(new UploadedFile($path, 'empty.docx')))
            ->toThrow(InvalidWordTemplate::class);

        @unlink($path);
    });

    it('refuses a file past the size cap', function () use ($docx) {
        $path = $docx(str_repeat('متن بلند ', 200));

        config(['exports.word.max_bytes' => 32]);

        expect(fn () => app(WordTemplateInspector::class)->inspect(new UploadedFile($path, 'big.docx')))
            ->toThrow(InvalidWordTemplate::class);

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

        $template = app(WordTemplateLibrary::class)->upload(
            new UploadedFile($path, 'hdr.docx', null, null, true),
            'boardmeeting',
            'سربرگ',
        );

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $file = app(WordTemplateService::class)->render(
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

        $this->library = app(WordTemplateLibrary::class);
        $this->store = app(WordTemplateStore::class);
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
            domain: 'boardmeeting',
            name: 'نامهٔ رسمی',
        );

        expect($template->key())->toBe('word/boardmeeting/official-letter')
            ->and($this->store->has($template))->toBeTrue()
            ->and($this->library->find('boardmeeting', 'official-letter'))->not->toBeNull();
    });

    it('keeps a Persian name out of the key', function () use ($upload) {
        $template = $this->library->upload(
            $upload('nama.docx'),
            domain: 'boardmeeting',
            name: 'نامهٔ رسمی جلسه',
        );

        expect($template->name())->toBe('نامهٔ رسمی جلسه')
            ->and($template->key())->toBe('word/boardmeeting/nama');
    });

    it('suffixes a second file of the same name rather than refusing it', function () use ($upload) {
        // Two people uploading "official.docx" is ordinary; being told to rename
        // the file is not help.
        $first = $this->library->upload($upload('official.docx'), 'boardmeeting', 'اول');
        $second = $this->library->upload($upload('official.docx'), 'boardmeeting', 'دوم');

        expect($first->slug())->toBe('official')
            ->and($second->slug())->toBe('official-2')
            ->and($this->library->forDomain('boardmeeting'))->toHaveCount(2);
    });

    it('falls back to a handle when the file name has nothing usable in it', function () use ($upload) {
        $template = $this->library->upload($upload('نامه رسمی.docx'), 'boardmeeting', 'نامه');

        // Inventing a transliteration would put a machine-made slug in a key
        // somebody may read in a log.
        expect($template->slug())->toStartWith('tpl-');
    });

    it('writes no row when the file cannot be accepted', function () use ($upload) {
        config(['exports.word.max_bytes' => 8]);

        try {
            $this->library->upload($upload('too-big.docx'), 'boardmeeting', 'بزرگ');
        } catch (InvalidWordTemplate) {
            // Expected.
        }

        // A row pointing at a template that cannot be exported would offer itself
        // in the picker and fail on use; that failure has to happen at upload.
        expect($this->library->forDomain('boardmeeting'))->toBeEmpty();
    });

    it('has one default per scope, and promoting another moves it', function () use ($upload) {
        $first = $this->library->upload($upload('a.docx'), 'boardmeeting', 'الف', isDefault: true);
        $second = $this->library->upload($upload('b.docx'), 'boardmeeting', 'ب');

        expect($this->library->defaultFor('boardmeeting')?->slug())->toBe('a');

        $this->library->promoteDefault($second);

        expect($this->library->defaultFor('boardmeeting')?->slug())->toBe('b')
            ->and($this->library->find('boardmeeting', 'a')?->isDefault())->toBeFalse();
    });

    it('keeps an entity-scoped template out of the domain list', function () use ($upload) {
        $this->library->upload($upload('domain.docx'), 'boardmeeting', 'دامنه');
        $this->library->upload($upload('meeting.docx'), 'boardmeeting', 'این جلسه', entity: 'session/42');

        expect($this->library->forDomain('boardmeeting'))->toHaveCount(1)
            ->and($this->library->forDomain('boardmeeting', 'session/42'))->toHaveCount(1);
    });

    it('gives the two scopes separate defaults', function () use ($upload) {
        $this->library->upload($upload('d.docx'), 'boardmeeting', 'دامنه', isDefault: true);
        $this->library->upload(
            $upload('m.docx'),
            'boardmeeting',
            'جلسه',
            entity: 'session/42',
            isDefault: true,
        );

        // One meeting having its own default must not silently take the domain's.
        expect($this->library->defaultFor('boardmeeting')?->slug())->toBe('d')
            ->and($this->library->defaultFor('boardmeeting', 'session/42')?->slug())->toBe('m');
    });

    it('removes the file as well as the row', function () use ($upload) {
        $template = $this->library->upload($upload('gone.docx'), 'boardmeeting', 'موقتی');

        expect($this->store->has($template))->toBeTrue();

        $this->library->delete($template);

        expect($this->store->has($template))->toBeFalse()
            ->and($this->library->find('boardmeeting', 'gone'))->toBeNull();
    });

    it('lists the default first', function () use ($upload) {
        $this->library->upload($upload('a.docx'), 'boardmeeting', 'الف');
        $this->library->upload($upload('z.docx'), 'boardmeeting', 'ز', isDefault: true);

        expect($this->library->forDomain('boardmeeting')[0]->slug())->toBe('z');
    });

    it('refuses a template with no name to recognise', function () use ($upload) {
        // A blank name is refused by the VALUE, not by the upload check: it is a
        // field the request validates, and the store's own strictness is what
        // stops a nameless row ever being written.
        expect(fn () => $this->library->upload($upload('x.docx'), 'boardmeeting', '   '))
            ->toThrow(InvalidSetting::class);
    });
});
