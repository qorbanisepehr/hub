<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Uploading a template that is not a Word file.
 *
 * THE PATH THAT WAS BROKEN, AND IT WAS BROKEN QUIETLY. There was no `format` field
 * at all: the controller defaulted to `docx` for anything it was not told otherwise,
 * the client never told it otherwise, and the form's `accept` attribute had been
 * widened to offer `.md` and `.json`. So the form actively invited a file it could
 * not accept — stored as a `docx`, then refused by the inspector opening Markdown as
 * a zip and reporting that it was not a Word document.
 *
 * The format is now DERIVED from the file rather than asked for, because the extension
 * is the only thing the person uploading did not have to be told about, and because a
 * format the caller states can disagree with the file it claims to be.
 */
describe('uploading a template in a non-word format', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    $manager = fn () => createUserWithPermissions(['export-template.manage', 'export-template.view']);

    /** A text template of the given format, as an upload. */
    $text = function (string $format, string $body): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'tpl-').'.'.$format;
        file_put_contents($path, $body);

        return new UploadedFile($path, 'letter.'.$format, null, null, true);
    };

    it('stores a markdown template as markdown, and reports what it holds', function () use ($manager, $text) {
        $response = $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $text('md', "# سربرگ\n\nعنوان: {{title}}\n\n{{content}}\n"),
                'name' => 'قالب متنی',
                'domain' => 'boardmeeting',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.format', 'md')
            // A `{{content}}` marker is what makes it a frame, and the upload is the
            // only moment anybody can act on which it is.
            ->assertJsonPath('inspection.kind', 'frame')
            ->assertJsonPath('inspection.placeholders', ['title', 'content']);
    });

    it('stores a json template as json', function () use ($manager, $text) {
        $response = $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $text('json', '{"title": "{{title}}", "meeting": {{content}}}'),
                'name' => 'قالب json',
                'domain' => 'boardmeeting',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.format', 'json')
            ->assertJsonPath('inspection.kind', 'frame');
    });

    it('stores a document template as a document, with no document generated', function () use ($manager, $text) {
        $response = $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => $text('md', 'عنوان: {{title}}'),
                'name' => 'نامه',
                'domain' => 'boardmeeting',
            ]);

        $response->assertCreated()->assertJsonPath('inspection.kind', 'detail');
    });

    it('lists a stored markdown template under markdown, not under word', function () use ($manager, $text) {
        // The other half of the same bug: stored correctly and then invisible,
        // because the library was asked for by a format the template was not.
        $this->actingAs($manager())->post('/api/word-templates', [
            'file' => $text('md', '# سربرگ'),
            'name' => 'قالب متنی',
            'domain' => 'boardmeeting',
        ])->assertCreated();

        $this->actingAs($manager())
            ->getJson('/api/word-templates?format=md&domain=boardmeeting')
            ->assertOk()
            ->assertJsonPath('data.templates.0.format', 'md');
    });

    it('refuses a format nobody can fill, and says which ones work', function () use ($manager, $text) {
        // A PDF is the real case: the export kernel renders one and it cannot take a
        // template, because the output is not editable. The message names the three
        // rather than saying "upload failed", because the person has to pick one.
        $response = $this->actingAs($manager())->post('/api/word-templates', [
            'file' => $text('pdf', '%PDF-1.4'),
            'name' => 'پی دی اف',
            'domain' => 'boardmeeting',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');

        expect($response->json('errors.file.0'))
            ->toContain('docx')
            ->toContain('md')
            ->toContain('json');
    });

    it('keeps a word template stored as a word template', function () use ($manager) {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('{{title}}');

        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $this->actingAs($manager())
            ->post('/api/word-templates', [
                'file' => new UploadedFile($path, 'letter.docx', null, null, true),
                'name' => 'نامه',
                'domain' => 'boardmeeting',
            ])
            ->assertCreated()
            ->assertJsonPath('data.format', 'docx');

        @unlink($path);
    });
});
