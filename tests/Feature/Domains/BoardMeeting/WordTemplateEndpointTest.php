<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\WordTemplateLibrary;
use App\Support\Exports\Templates\WordTemplateStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * The Word template library over HTTP, and a template chosen at export time.
 *
 * The two halves are one feature seen from either end: a person uploads a file
 * and later exports with it. What matters is that they agree — a template the
 * picker offers must be one the export will actually use, and one the export
 * refuses must not appear as if it would work.
 */
describe('the word template library endpoints', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->library = app(WordTemplateLibrary::class);
        $this->store = app(WordTemplateStore::class);
    });

    /** A real docx upload. */
    $upload = function (string $filename, ?string $text = 'متن') {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text ?? 'متن');

        $path = tempnam(sys_get_temp_dir(), 'api-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return new UploadedFile($path, $filename, null, null, true);
    };

    $manager = fn () => createUserWithPermissions(['export-template.manage', 'export-template.view']);
    $reader = fn () => createUserWithPermissions(['export-template.view']);

    it('starts empty', function () use ($reader) {
        $this->actingAs($reader())
            ->getJson('/api/word-templates')
            ->assertOk()
            ->assertJsonPath('data.domain', BoardMeetingSheetExport::TEMPLATE_DOMAIN)
            ->assertJsonPath('data.templates', []);
    });

    it('uploads a template and reports it without a kind', function () use ($manager, $upload) {
        $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $upload('official.docx', '{{title}}'),
                'name' => 'نامهٔ رسمی',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'نامهٔ رسمی')
            // It used to say `kind: content` or `kind: style`, which is what told
            // a person whether their file needed placeholders to do anything. It
            // told them the wrong thing about a letterhead with a placeholder in
            // its footer, so there is no kind to report.
            ->assertJsonMissingPath('data.kind');
    });

    it('reports a template with no placeholders the same way', function () use ($manager, $upload) {
        $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $upload('house.docx', 'فقط سربرگ'),
                'name' => 'سبک خانه',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertCreated()
            ->assertJsonMissingPath('data.kind');
    });

    it('answers 422 with the reason when the file is refused', function () use ($manager, $upload) {
        config(['exports.word.max_bytes' => 8]);

        $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $upload('big.docx'),
                'name' => 'بزرگ',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    });

    it('refuses a domain no template exists for', function () use ($manager, $upload) {
        $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $upload('x.docx'),
                'name' => 'x',
                'domain' => 'not-a-domain',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    });

    it('lists what a template may contain, grouped so repeating rows are visible', function () use ($reader) {
        // The grouping is the point: a flat list of 28 names cannot say which of
        // them have to share a table row, and that is the one mistake that makes
        // a list render as a single line.
        $this->actingAs($reader())
            ->getJson('/api/word-templates/fields')
            ->assertOk()
            ->assertJsonPath('data.syntax', ['open' => '{{', 'close' => '}}'])
            ->assertJsonStructure([
                'data' => [
                    'inline' => [['key', 'label']],
                    'rows' => [['group', 'columns' => [['key', 'label']]]],
                ],
            ]);

        $groups = $this->actingAs($reader())
            ->getJson('/api/word-templates/fields')
            ->json('data.rows');

        expect(collect($groups)->pluck('group')->all())
            ->toContain('attendee', 'agenda', 'tag');
    });

    it('promotes a default, which is what the export button then uses', function () use ($manager, $upload) {
        $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $upload('a.docx'),
                'name' => 'الف',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ]);

        $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $upload('b.docx'),
                'name' => 'ب',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertCreated();

        $this->actingAs($manager())
            ->putJson('/api/word-templates/b', ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $listed = $this->actingAs($manager())
            ->getJson('/api/word-templates')
            ->json('data.templates');

        // Default first, so the list a person sees leads with the one the button
        // would use.
        expect($listed[0]['slug'])->toBe('b');
    });

    it('renames a template without touching its file', function () use ($manager, $upload) {
        $template = $this->library->upload($upload('x.docx'), 'boardmeeting', 'قبلی');

        $this->actingAs($manager())
            ->putJson('/api/word-templates/'.$template->slug(), ['name' => 'بعدی'])
            ->assertOk()
            ->assertJsonPath('data.name', 'بعدی');

        expect($this->store->has($template))->toBeTrue();
    });

    it('deletes a template and its file', function () use ($manager, $upload) {
        $template = $this->library->upload($upload('gone.docx'), 'boardmeeting', 'موقتی');

        $this->actingAs($manager())
            ->deleteJson('/api/word-templates/'.$template->slug())
            ->assertOk();

        expect($this->store->has($template))->toBeFalse();
    });

    it('answers 404 for a template that is not there', function () use ($manager) {
        $this->actingAs($manager())
            ->deleteJson('/api/word-templates/nope')
            ->assertNotFound();
    });

    it('keeps management out of a reader’s hands', function () use ($reader, $upload) {
        $this->actingAs($reader())
            ->post('/api/word-templates', [
                'file' => $upload('x.docx'),
                'name' => 'x',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertForbidden();

        $this->actingAs($reader())
            ->deleteJson('/api/word-templates/anything')
            ->assertForbidden();
    });
});

describe('exporting with a word template', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->library = app(WordTemplateLibrary::class);
        $this->store = app(WordTemplateStore::class);
    });

    /** A real docx upload carrying the given body. */
    $upload = function (string $filename, string $text) {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);

        $path = tempnam(sys_get_temp_dir(), 'exp-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return new UploadedFile($path, $filename, null, null, true);
    };

    it('generates the document when nobody has configured a template', function () {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    });

    it('uses the default template without being asked for one', function () use ($upload) {
        $this->library->upload($upload('house.docx', 'سربرگ هیئت مدیره'), 'boardmeeting', 'سبک خانه', isDefault: true);

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx")
            ->assertOk();

        $out = tempnam(sys_get_temp_dir(), 'out-').'.docx';
        file_put_contents($out, $response->streamedContent());

        $zip = new ZipArchive;
        $zip->open($out);
        $text = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // The style template's own body survived, and the meeting is under it.
        expect($text)->toContain('سربرگ هیئت مدیره')
            ->and($text)->toContain('بودجه');

        @unlink($out);
    });

    it('uses the template that was named, not the default', function () use ($upload) {
        $this->library->upload($upload('house.docx', 'سبک پیش‌فرض'), 'boardmeeting', 'پیش‌فرض', isDefault: true);
        $this->library->upload($upload('other.docx', 'سبک دیگر'), 'boardmeeting', 'دیگر');

        $meeting = BoardMeeting::factory()->create();

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx?template=other")
            ->assertOk();

        $out = tempnam(sys_get_temp_dir(), 'out-').'.docx';
        file_put_contents($out, $response->streamedContent());

        $zip = new ZipArchive;
        $zip->open($out);
        $text = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        expect($text)->toContain('سبک دیگر')
            ->and($text)->not->toContain('سبک پیش‌فرض');

        @unlink($out);
    });

    it('answers 404 for a template nobody has, rather than quietly using the default', function () use ($upload) {
        // Falling back here would hand somebody a different document than the one
        // they asked for, with nothing to tell them so.
        $this->library->upload($upload('house.docx', 'سبک'), 'boardmeeting', 'سبک', isDefault: true);

        $meeting = BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx?template=nope")
            ->assertNotFound();
    });

    it('fills the template placeholders and still adds the meeting', function () use ($upload) {
        // This used to be called "fills a content template rather than generating
        // over it", and the choice between those two was the whole bug: the file
        // was classified by whether it held a placeholder, and a letterhead with
        // {{date}} in its footer was therefore treated as one that REPLACES the
        // meeting. Both halves have to happen now, for every template.
        $this->library->upload(
            $upload('letter.docx', 'صورتجلسه {{title}} به تاریخ {{held_on}}'),
            'boardmeeting',
            'نامه',
            isDefault: true,
        );

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجهٔ سالانه']);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx")
            ->assertOk();

        $out = tempnam(sys_get_temp_dir(), 'out-').'.docx';
        file_put_contents($out, $response->streamedContent());

        $zip = new ZipArchive;
        $zip->open($out);
        $text = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // Filled: the macro is gone and its value is in the document.
        expect($text)->not->toContain('{{title}}')
            ->not->toContain('{{held_on}}')
            // AND the meeting was generated, not just substituted into the frame.
            ->toContain(substr_count($text, 'بودجهٔ سالانه'))->toBeGreaterThan(1);

        @unlink($out);
    });
});

/**
 * A bundle's Word sheets are built from the same template a single export uses.
 *
 * Separate from the single-export cases above because the failure it guards is
 * the kind nobody sees: an archive of fifty files in the wrong shape is not a
 * broken download, it is fifty documents somebody has to open to discover. And
 * the queued path is separate again, because it resolves the template minutes
 * later — a world in which the template may since have been deleted.
 */
describe('a board meeting bundle with a word template', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->library = app(WordTemplateLibrary::class);
    });

    $upload = function (string $filename, string $text) {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);

        $path = tempnam(sys_get_temp_dir(), 'bnd-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return new UploadedFile($path, $filename, null, null, true);
    };

    /**
     * The document.xml of the first `.docx` in an archive.
     *
     * Self-contained on purpose: the equivalent helpers in the bulk-bundle tests
     * are plain functions in that file, so they only exist once that file has
     * run. A test borrowing them would pass alone and fail in a random order.
     */
    $firstDocx = function (string $archive): string {
        $outer = tempnam(sys_get_temp_dir(), 'arc-').'.zip';
        file_put_contents($outer, $archive);

        $zip = new ZipArchive;
        $zip->open($outer);

        $inner = null;
        for ($index = 0; $index < $zip->numFiles && $inner === null; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_ends_with($name, '.docx')) {
                $inner = tempnam(sys_get_temp_dir(), 'in-').'.docx';
                file_put_contents($inner, (string) $zip->getFromIndex($index));
            }
        }
        $zip->close();
        @unlink($outer);

        expect($inner)->not->toBeNull('the archive held no Word document');

        $docx = new ZipArchive;
        $docx->open($inner);
        $text = (string) $docx->getFromName('word/document.xml');
        $docx->close();
        @unlink($inner);

        return $text;
    };

    it('uses the domain default when the caller names none', function () use ($upload, $firstDocx) {
        $this->library->upload($upload('house.docx', 'سربرگ هیئت مدیره'), 'boardmeeting', 'سبک خانه', isDefault: true);

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $archive = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['docx']])
            ->assertOk()
            ->streamedContent();

        // The frame is there and the meeting is under it, which is what makes
        // this different from generating a document and ignoring the template.
        expect($firstDocx($archive))
            ->toContain('سربرگ هیئت مدیره')
            ->toContain('بودجه');
    });

    it('uses the template that was named, not the default', function () use ($upload, $firstDocx) {
        $this->library->upload($upload('house.docx', 'سربرگ هیئت مدیره'), 'boardmeeting', 'پیش‌فرض', isDefault: true);
        $this->library->upload($upload('other.docx', 'سربرگ شورایا'), 'boardmeeting', 'دیگر');

        $meeting = BoardMeeting::factory()->create();

        // The slug comes from the FILE name - `other.docx` - and is never the display
        // name, which is what a person reading this test would reach for first.
        $archive = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['docx'],
                'word_template' => 'other',
            ])
            ->assertOk()
            ->streamedContent();

        expect($firstDocx($archive))
            ->toContain('سربرگ شورایا')
            ->not->toContain('سربرگ هیئت مدیره');
    });

    it('answers 404 for a template nobody has rather than quietly using the default', function () use ($upload) {
        $this->library->upload($upload('house.docx', 'سبک'), 'boardmeeting', 'پیش‌فرض', isDefault: true);

        BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['docx'],
                'word_template' => 'nope',
            ])
            ->assertNotFound();
    });

    it('answers 404 for a slug that could never be a key, rather than falling over', function () use ($upload) {
        // A person typing the template's Persian NAME into the slug field is the
        // obvious mistake, and the store throws on a key it does not handle. The
        // question "which template is دیگر" has an answer - none - and that
        // answer is a 404, not a server error.
        $this->library->upload($upload('other.docx', 'سبک دیگر'), 'boardmeeting', 'دیگر', isDefault: true);

        BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['docx'],
                'word_template' => 'دیگر',
            ])
            ->assertNotFound();
    });

    it('refuses a template that is not a name', function () {
        BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', [
                'formats' => ['docx'],
                'word_template' => ['../../etc/passwd'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('word_template');
    });

    it('generates plain documents when the domain has no template at all', function () use ($firstDocx) {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $archive = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['docx']])
            ->assertOk()
            ->streamedContent();

        expect($firstDocx($archive))->toContain('بودجه');
    });
});
