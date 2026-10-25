<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\FileTemplate;
use App\Support\Exports\Templates\FileTemplateLibrary;
use App\Support\Exports\Templates\FileTemplateStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
describe('the template library endpoints', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->library = app(FileTemplateLibrary::class);
        $this->store = app(FileTemplateStore::class);
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

    it('audits upload, change and delete of a template', function () use ($manager, $upload) {
        // F2: a template carries the board's letterhead, so "who put this file
        // here" and "who took it away" are the questions the log exists for. The
        // actors differ on purpose: one person uploading and another promoting is
        // the shape that proves the event follows the request, not the row.
        $author = $manager();
        $editor = $manager();

        $this->actingAs($author)
            ->post('/api/templates', [
                'file' => $upload('letterhead.docx'),
                'name' => 'سربرگ',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertCreated();

        $this->actingAs($editor)
            ->putJson('/api/templates/letterhead', ['name' => 'سربرگ رسمی', 'is_default' => true])
            ->assertOk();

        $this->actingAs($editor)
            ->deleteJson('/api/templates/letterhead')
            ->assertOk();

        $log = AuditLog::query()->orderBy('id')->get();

        expect($log->pluck('event')->all())->toBe([
            'boardmeeting.file_template.uploaded',
            'boardmeeting.file_template.updated',
            'boardmeeting.file_template.deleted',
        ])
            ->and($log->pluck('actor_id')->all())->toBe([$author->id, $editor->id, $editor->id])
            ->and($log->last()->subject_type)->toBe('file-template');

        // One PATCH that changes two things stays one event: two rows a
        // millisecond apart would read as two separate actions.
        expect($log[1]->old_values)->toBe(['name' => 'سربرگ', 'is_default' => false])
            ->and($log[1]->new_values)->toBe(['name' => 'سربرگ رسمی', 'is_default' => true]);
    });

    it('starts empty', function () use ($reader) {
        $this->actingAs($reader())
            ->getJson('/api/templates')
            ->assertOk()
            ->assertJsonPath('data.domain', BoardMeetingSheetExport::TEMPLATE_DOMAIN)
            ->assertJsonPath('data.templates', []);
    });

    it('uploads a template and reports it without a kind', function () use ($manager, $upload) {
        $this->actingAs($manager())
            ->post('/api/templates', [
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
            ->post('/api/templates', [
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
            ->post('/api/templates', [
                'file' => $upload('big.docx'),
                'name' => 'بزرگ',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    });

    it('refuses a domain no template exists for', function () use ($manager, $upload) {
        $this->actingAs($manager())
            ->post('/api/templates', [
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
            ->getJson('/api/templates/fields')
            ->assertOk()
            ->assertJsonPath('data.syntax', ['open' => '{{', 'close' => '}}', 'marker' => 'content'])
            ->assertJsonStructure([
                'data' => [
                    'inline' => [['key', 'label']],
                    'rows' => [['group', 'columns' => [['key', 'label']]]],
                ],
            ]);

        $groups = $this->actingAs($reader())
            ->getJson('/api/templates/fields')
            ->json('data.rows');

        expect(collect($groups)->pluck('group')->all())
            ->toContain('attendee', 'agenda', 'tag');
    });

    it('lists the general names and says which section each meeting name appears in', function () use ($reader) {
        // The cheat sheet grew these, and the client reads them defensively — an
        // older server gets an empty group rather than a crash — which means a
        // missing key here would show up as an empty panel on the screen and nothing
        // anywhere else. Asserted on the wire for that reason.
        $data = $this->actingAs($reader())
            ->getJson('/api/templates/fields')
            ->assertOk()
            ->json('data');

        expect(collect($data['general'])->pluck('key')->all())
            ->toBe(['export_date', 'created_date', 'domain_label', 'entity_label']);

        // Every label has to be a WORD. An untranslated key still renders as a
        // string, so the cheat sheet would look populated while saying nothing.
        foreach ($data['general'] as $field) {
            expect($field['label'])->toBeString()->not->toBe($field['key']);
        }

        $sections = collect($data['sections']);

        expect($sections->pluck('key')->all())
            ->toBe(app(BoardMeetingSheetExport::class)->sectionNames());

        // The question the profile exists to answer: a name has to be findable from
        // where it appears, not only listed.
        $attendees = $sections->firstWhere('key', 'attendees');

        expect($attendees['fields'])->toContain('attendee.name', 'attendee.role')
            ->and($attendees['label'])->toBeString()->not->toBe('');
    });

    it('promotes a default, which is what the export button then uses', function () use ($manager, $upload) {
        $this->actingAs($manager())
            ->post('/api/templates', [
                'file' => $upload('a.docx'),
                'name' => 'الف',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ]);

        $this->actingAs($manager())
            ->post('/api/templates', [
                'file' => $upload('b.docx'),
                'name' => 'ب',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertCreated();

        $this->actingAs($manager())
            ->putJson('/api/templates/b', ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $listed = $this->actingAs($manager())
            ->getJson('/api/templates')
            ->json('data.templates');

        // Default first, so the list a person sees leads with the one the button
        // would use.
        expect($listed[0]['slug'])->toBe('b');
    });

    it('renames a template without touching its file', function () use ($manager, $upload) {
        $template = $this->library->upload($upload('x.docx'), 'docx', 'boardmeeting', 'قبلی');

        $this->actingAs($manager())
            ->putJson('/api/templates/'.$template->slug(), ['name' => 'بعدی'])
            ->assertOk()
            ->assertJsonPath('data.name', 'بعدی');

        expect($this->store->has($template))->toBeTrue();
    });

    it('keeps who last changed a template, through rename and promote', function () use ($manager, $upload) {
        // The stamp is the only attribution a template write has (F2: no audit events
        // yet), and all three of these rewrite the row. They used to write
        // `updated_by = null` — losing the uploader on the operations that most
        // deserve a name.
        $stampOf = fn (FileTemplate $template): mixed => DB::table('system_settings')
            ->where('group', FileTemplate::group())
            ->where('key', $template->key())
            ->value('updated_by');

        $author = $manager();
        $editor = $manager();

        $first = $this->library->upload(
            $upload('a.docx'), 'docx', 'boardmeeting', 'الف',
            isDefault: true, uploadedBy: $author->getKey(),
        );

        $second = $this->library->upload(
            $upload('b.docx'), 'docx', 'boardmeeting', 'ب',
            uploadedBy: $author->getKey(),
        );

        $this->actingAs($editor)
            ->putJson('/api/templates/'.$first->slug(), ['name' => 'الف ویرایش‌شده'])
            ->assertOk();

        expect($stampOf($first))->toBe($editor->getKey());

        $this->actingAs($editor)
            ->putJson('/api/templates/'.$second->slug(), ['is_default' => true])
            ->assertOk();

        // Promoting clears the other default, and that rewrite is a change to the
        // first template too — attributed to whoever cleared it, not to nobody.
        expect($stampOf($second))->toBe($editor->getKey())
            ->and($stampOf($first))->toBe($editor->getKey())
            ->and($stampOf($first))->not->toBeNull();
    });

    it('deletes a template and its file', function () use ($manager, $upload) {
        $template = $this->library->upload($upload('gone.docx'), 'docx', 'boardmeeting', 'موقتی');

        $this->actingAs($manager())
            ->deleteJson('/api/templates/'.$template->slug())
            ->assertOk();

        expect($this->store->has($template))->toBeFalse();
    });

    it('answers 404 for a template that is not there', function () use ($manager) {
        $this->actingAs($manager())
            ->deleteJson('/api/templates/nope')
            ->assertNotFound();
    });

    it('keeps management out of a reader’s hands', function () use ($reader, $upload) {
        $this->actingAs($reader())
            ->post('/api/templates', [
                'file' => $upload('x.docx'),
                'name' => 'x',
                'domain' => BoardMeetingSheetExport::TEMPLATE_DOMAIN,
            ])
            ->assertForbidden();

        $this->actingAs($reader())
            ->deleteJson('/api/templates/anything')
            ->assertForbidden();
    });
});

describe('exporting with a template', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->library = app(FileTemplateLibrary::class);
        $this->store = app(FileTemplateStore::class);
    });

    /** A real docx upload carrying the given body. */
    $upload = function (string $filename, string $text) {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);

        $path = tempnam(sys_get_temp_dir(), 'exp-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return new UploadedFile($path, $filename, null, null, true);
    };

    /** A plain-text upload; the library argument decides which format it is. */
    $text = function (string $filename, string $content): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'txt-').'-'.$filename;
        file_put_contents($path, $content);

        return new UploadedFile($path, $filename, null, null, true);
    };

    $previewer = fn () => createUserWithPermissions([
        'export-template.manage',
        'board-meeting.export',
    ]);

    it('generates the document when nobody has configured a template', function () {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    });

    it('uses the default template without being asked for one', function () use ($upload) {
        $this->library->upload($upload('house.docx', 'سربرگ هیئت مدیره'), 'docx', 'boardmeeting', 'سبک خانه', isDefault: true);

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
        $this->library->upload($upload('house.docx', 'سبک پیش‌فرض'), 'docx', 'boardmeeting', 'پیش‌فرض', isDefault: true);
        $this->library->upload($upload('other.docx', 'سبک دیگر'), 'docx', 'boardmeeting', 'دیگر');

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
        $this->library->upload($upload('house.docx', 'سبک'), 'docx', 'boardmeeting', 'سبک', isDefault: true);

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
            'docx',
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

    it('fills a markdown template, defaulting to it like Word does', function () use ($text) {
        // The dialog has offered Markdown templates since it was written; the
        // export route ignored `?template=` and always generated the sheet. A
        // default here is the same promise the Word route keeps: unnamed means
        // the domain's chosen frame, not "no frame".
        $this->library->upload(
            $text('frame.md', "چارچوب\n\n# {{title}}\n\n{{content}}\n"),
            'md',
            'boardmeeting',
            'فریم مارک‌داون',
            isDefault: true,
        );

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجهٔ سالانه']);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/md")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

        $body = $response->streamedContent();

        expect($body)->toContain('چارچوب')
            ->and($body)->toContain('# بودجهٔ سالانه')
            ->and($body)->not->toContain('{{');
    });

    it('previews a template with the newest meeting', function () use ($text, $previewer) {
        $this->library->upload(
            $text('frame.md', "# {{title}}\n\n{{content}}\n"),
            'md',
            'boardmeeting',
            'پیش‌نمایش',
        );

        BoardMeeting::factory()->create([
            'title' => 'جلسهٔ قدیمی',
            'held_on' => now()->subDay(),
        ]);
        BoardMeeting::factory()->create([
            'title' => 'جلسهٔ جدید',
            'held_on' => now(),
        ]);

        $response = $this->actingAs($previewer())
            ->post('/api/templates/frame/preview?format=md')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

        $body = $response->streamedContent();

        expect($body)->toContain('# جلسهٔ جدید')
            ->and($body)->not->toContain('جلسهٔ قدیمی');
    });

    it('refuses a preview when there is no meeting yet', function () use ($text, $previewer) {
        $this->library->upload(
            $text('frame.md', "{{content}}\n"),
            'md',
            'boardmeeting',
            'پیش‌نمایش',
        );

        $this->actingAs($previewer())
            ->postJson('/api/templates/frame/preview?format=md')
            ->assertStatus(422)
            ->assertJsonPath('message', __('board_meeting.template.preview_no_meeting'));
    });

    it('needs both template management and meeting export to preview', function () use ($text) {
        $this->library->upload(
            $text('frame.md', "{{content}}\n"),
            'md',
            'boardmeeting',
            'پیش‌نمایش',
        );

        BoardMeeting::factory()->create(['title' => 'جلسه']);

        $this->actingAs(createUserWithPermissions(['export-template.manage']))
            ->post('/api/templates/frame/preview?format=md')
            ->assertForbidden();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->post('/api/templates/frame/preview?format=md')
            ->assertForbidden();
    });

    it('answers 404 for a preview of a template that is not there', function () use ($previewer) {
        BoardMeeting::factory()->create(['title' => 'جلسه']);

        $this->actingAs($previewer())
            ->post('/api/templates/missing/preview?format=md')
            ->assertNotFound();
    });

    it('lets one export opt out of the default with without_template', function () use ($text) {
        // Absent means the default; the flag means the generated sheet. The dialog
        // needs both, and conflating them was the row that claimed "no template"
        // while the server quietly applied one.
        $this->library->upload(
            $text('frame.md', "چارچوب\n\n{{content}}\n"),
            'md',
            'boardmeeting',
            'فریم',
            isDefault: true,
        );

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجهٔ سالانه']);

        $body = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/md?without_template=1")
            ->assertOk()
            ->streamedContent();

        expect($body)->not->toContain('چارچوب')
            ->and($body)->toContain('صورتجلسهٔ هیئت مدیره');
    });

    it('fills a json template that was named', function () use ($text) {
        $template = $this->library->upload(
            $text('frame.json', '{"frame": "چارچوب", "title": "{{title}}", "sheet": {{content}}}'),
            'json',
            'boardmeeting',
            'فریم جیسون',
        );

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجهٔ سالانه']);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/json?template={$template->slug()}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');

        $decoded = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

        expect($decoded['frame'])->toBe('چارچوب')
            ->and($decoded['title'])->toBe('بودجهٔ سالانه')
            ->and($decoded['sheet'])->toBeArray();
    });

    it('refuses a Word template on the markdown route', function () use ($upload) {
        // The format is part of the lookup. Falling back to "fill it anyway"
        // would return Word bytes under a `.md` name.
        $template = $this->library->upload($upload('letter.docx', 'سربرگ'), 'docx', 'boardmeeting', 'سربرگ');

        $meeting = BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/md?template={$template->slug()}")
            ->assertNotFound();
    });

    it('answers 422 when a json template does not produce json', function () use ($text) {
        // The refusal used to be a 500 from a bare RuntimeException; an author
        // needs a 422 that says the template is the thing to fix.
        $template = $this->library->upload(
            $text('broken.json', '{"title": {{title}}}'),
            'json',
            'boardmeeting',
            'فریم شکسته',
        );

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/json?template={$template->slug()}")
            ->assertStatus(422)
            ->assertJsonPath('message', __('exports.template_invalid_json'));
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
describe('a board meeting bundle with a template', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->library = app(FileTemplateLibrary::class);
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
        $this->library->upload($upload('house.docx', 'سربرگ هیئت مدیره'), 'docx', 'boardmeeting', 'سبک خانه', isDefault: true);

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
        $this->library->upload($upload('house.docx', 'سربرگ هیئت مدیره'), 'docx', 'boardmeeting', 'پیش‌فرض', isDefault: true);
        $this->library->upload($upload('other.docx', 'سربرگ شورایا'), 'docx', 'boardmeeting', 'دیگر');

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
        $this->library->upload($upload('house.docx', 'سبک'), 'docx', 'boardmeeting', 'پیش‌فرض', isDefault: true);

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
        $this->library->upload($upload('other.docx', 'سبک دیگر'), 'docx', 'boardmeeting', 'دیگر', isDefault: true);

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

    it('uses a markdown default inside the archive, like a single export', function () {
        // Before this, a bundle's Markdown sheet was always generated: the
        // template library could hold a `.md` default that no archive honoured.
        // A configured frame has to mean the same thing on both surfaces, or the
        // person who set it has no way to know which file will be shaped how.
        $path = tempnam(sys_get_temp_dir(), 'md-').'-frame.md';
        file_put_contents($path, "چارچوب\n\n# {{title}}\n\n{{content}}\n");

        $this->library->upload(
            new UploadedFile($path, 'frame.md', null, null, true),
            'md',
            'boardmeeting',
            'فریم مارک‌داون',
            isDefault: true,
        );

        BoardMeeting::factory()->create(['title' => 'بودجه']);

        $archive = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->postJson('/api/board-meetings/export/bundle', ['formats' => ['md']])
            ->assertOk()
            ->streamedContent();

        $outer = tempnam(sys_get_temp_dir(), 'arc-').'.zip';
        file_put_contents($outer, $archive);

        $zip = new ZipArchive;
        $zip->open($outer);

        $markdown = '';

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if ($name !== 'manifest.md' && str_ends_with($name, '.md')) {
                $markdown = (string) $zip->getFromName($name);
            }
        }

        $zip->close();
        @unlink($outer);

        expect($markdown)->toContain('چارچوب')
            ->toContain('# بودجه')
            ->not->toContain('{{');
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
