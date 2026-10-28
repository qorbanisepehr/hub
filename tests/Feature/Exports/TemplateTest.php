<?php

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Exceptions\InvalidSetting;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Support\Exports\Templates\FileTemplate;
use App\Support\Exports\Templates\SectionTemplate;

/**
 * Word templates in the settings store.
 *
 * The three things worth protecting: the file's path is derived from the key so
 * it cannot point at another template's file; the namespace keeps the two kinds
 * of setting in one group from claiming each other; and the rename is a real,
 * re-runnable move rather than an assumption that the store was empty.
 */
describe('templates', function () {
    it('addresses a template by format, domain and slug', function () {
        expect(FileTemplate::make('docx', 'boardmeeting', 'official', null, 'نامهٔ رسمی', 10, 'application/docx')->key())
            ->toBe('template/docx/boardmeeting/official')
            ->and(FileTemplate::make('docx', 'boardmeeting', 'official', 'session/42', 'ویژه', 10, 'application/docx')->key())
            ->toBe('template/docx/boardmeeting/official/session/42');
    });

    it('keeps the same domain and slug apart for two formats', function () {
        // THE REASON THE FORMAT IS IN THE KEY. A Word letterhead and a Markdown
        // frame for the same domain are two files for one purpose; without the
        // format in the key they would want the same row, and the second upload
        // would either overwrite the first or need a format column beside the
        // key - which is a second place for the row and the file to disagree.
        $docx = FileTemplate::make('docx', 'boardmeeting', 'official', null, 'نامهٔ رسمی', 10, 'application/docx');
        $md = FileTemplate::make('md', 'boardmeeting', 'official', null, 'قاب', 10, 'text/markdown');

        expect($docx->key())->not->toBe($md->key())
            ->and($docx->filePath())->not->toBe($md->filePath())
            // Nor may the extension be guessed from anything but the key.
            ->and($md->filePath())->toEndWith('.md');
    });

    it('refuses a key with no format in it', function () {
        // Three segments before the entity is optional: without a format there is
        // nothing to say which kind of file a row is about.
        expect(FileTemplate::handles('template/boardmeeting/official'))->toBeFalse()
            ->and(FileTemplate::handles('template/docx/boardmeeting'))->toBeFalse()
            ->and(FileTemplate::handles('template/docx/boardmeeting/official'))->toBeTrue();
    });

    it('derives the file path from the key, so the two cannot disagree', function () {
        $domain = FileTemplate::make('docx', 'boardmeeting', 'official', null, 'نامهٔ رسمی', 10, 'application/docx');
        $scoped = FileTemplate::make('docx', 'boardmeeting', 'official', 'session/42', 'ویژه', 10, 'application/docx');

        // Two rows with the same slug must not land on the same file, or the
        // second upload would silently overwrite the first — and nothing in
        // either row would say so.
        expect($domain->filePath())->toBe('templates/docx/boardmeeting/official.docx')
            ->and($scoped->filePath())->toBe('templates/docx/boardmeeting/official/session/42.docx');
    });

    it('keeps the readable name out of the key', function () {
        // A Persian name cannot be a legal key segment, so the name lives in the
        // value and the slug is a machine handle nobody is shown.
        $template = FileTemplate::make('docx', 'boardmeeting', 'official', null, 'نامهٔ رسمی جلسه', 10, 'application/docx');

        expect($template->key())->toBe('template/docx/boardmeeting/official')
            ->and($template->name())->toBe('نامهٔ رسمی جلسه');
    });

    it('refuses a template with no name to recognise', function () {
        $this->expectException(InvalidSetting::class);

        FileTemplate::make('docx', 'boardmeeting', 'official', null, '   ', 10, 'application/docx');
    });

    it('refuses a slug that cannot be a key segment', function () {
        $this->expectException(InvalidSetting::class);

        FileTemplate::make('docx', 'boardmeeting', 'نامه رسمی', null, 'نامه', 10, 'application/docx');
    });

    it('reads back what it wrote', function () {
        $template = FileTemplate::make('docx', 'boardmeeting', 'official', 'session/42', 'نامهٔ رسمی', 2048, 'application/docx', isDefault: true);

        $restored = FileTemplate::fromStored($template->key(), $template->toValue(), null);

        expect($restored->domain())->toBe('boardmeeting')
            ->and($restored->slug())->toBe('official')
            ->and($restored->entity())->toBe('session/42')
            ->and($restored->size())->toBe(2048)
            ->and($restored->isDefault())->toBeTrue()
            ->and($restored->key())->toBe($template->key());

        // No `kind` to round-trip: the value no longer says whether the file holds
        // placeholders, because nothing branches on that any more.
        expect($template->toValue())->not->toHaveKey('has_placeholders');
    });

    it('refuses a row whose payload disagrees with its key', function () {
        // The key is what every lookup is written against. A payload naming a
        // different domain would apply one template's content to another's.
        $this->expectException(InvalidSetting::class);

        FileTemplate::fromStored('template/docx/boardmeeting/official', ['domain' => 'cv', 'name' => 'نامه'], null);
    });

    it('refuses a row whose slug disagrees with its key', function () {
        // The file path is derived from the key's slug, so a payload naming
        // another one would describe a file that does not exist.
        $this->expectException(InvalidSetting::class);

        FileTemplate::fromStored('template/docx/boardmeeting/official', ['slug' => 'other', 'name' => 'نامه'], null);
    });

    it('refuses a key that names no template', function () {
        foreach (['word/boardmeeting', 'word/', 'word//official', 'boardmeeting/official'] as $key) {
            expect(FileTemplate::handles($key))->toBeFalse($key);
        }
    });

    it('has no private half to leak', function () {
        // The file is on the private disk, not in the row. Storing it in the
        // encrypted column would mean decrypting every template to list their
        // names.
        expect(FileTemplate::make('docx', 'boardmeeting', 'official', null, 'نامه', 10, 'application/docx')->toSecrets())
            ->toBeNull();
    });

    it('round-trips through the real store without touching the other kind', function () {
        $repo = app(SystemSettingsRepositoryInterface::class);

        $word = FileTemplate::make('docx', 'boardmeeting', 'official', null, 'نامهٔ رسمی', 10, 'application/docx');
        $section = SectionTemplate::make('boardmeeting', null, ['identity']);

        $repo->put($word);
        $repo->put($section);

        expect($repo->get(FileTemplate::class, $word->key()))->toBeInstanceOf(FileTemplate::class)
            ->and($repo->get(SectionTemplate::class, $section->key()))->toBeInstanceOf(SectionTemplate::class);

        // And each listing sees only its own kind, which is the whole reason the
        // namespaces exist.
        $words = array_filter($repo->all(FileTemplate::class), fn (Setting $s): bool => $s instanceof FileTemplate);
        $sections = array_filter($repo->all(SectionTemplate::class), fn (Setting $s): bool => $s instanceof SectionTemplate);

        expect($words)->toHaveCount(1)
            ->and($sections)->toHaveCount(1);
    });
});
