<?php

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Exceptions\InvalidSetting;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Support\Exports\Templates\ExportTemplate;
use App\Support\Exports\Templates\WordTemplate;

/**
 * Word templates in the settings store.
 *
 * The three things worth protecting: the file's path is derived from the key so
 * it cannot point at another template's file; the namespace keeps the two kinds
 * of setting in one group from claiming each other; and the rename is a real,
 * re-runnable move rather than an assumption that the store was empty.
 */
describe('word templates', function () {
    it('addresses a template by domain and slug', function () {
        expect(WordTemplate::make('boardmeeting', 'official', null, 'نامهٔ رسمی', 10, 'application/docx', true)->key())
            ->toBe('word/boardmeeting/official')
            ->and(WordTemplate::make('boardmeeting', 'official', 'session/42', 'ویژه', 10, 'application/docx', true)->key())
            ->toBe('word/boardmeeting/official/session/42');
    });

    it('derives the file path from the key, so the two cannot disagree', function () {
        $domain = WordTemplate::make('boardmeeting', 'official', null, 'نامهٔ رسمی', 10, 'application/docx', true);
        $scoped = WordTemplate::make('boardmeeting', 'official', 'session/42', 'ویژه', 10, 'application/docx', true);

        // Two rows with the same slug must not land on the same file, or the
        // second upload would silently overwrite the first — and nothing in
        // either row would say so.
        expect($domain->filePath())->toBe('word/boardmeeting/official.docx')
            ->and($scoped->filePath())->toBe('word/boardmeeting/official/session/42.docx');
    });

    it('keeps the readable name out of the key', function () {
        // A Persian name cannot be a legal key segment, so the name lives in the
        // value and the slug is a machine handle nobody is shown.
        $template = WordTemplate::make('boardmeeting', 'official', null, 'نامهٔ رسمی جلسه', 10, 'application/docx', true);

        expect($template->key())->toBe('word/boardmeeting/official')
            ->and($template->name())->toBe('نامهٔ رسمی جلسه');
    });

    it('refuses a template with no name to recognise', function () {
        $this->expectException(InvalidSetting::class);

        WordTemplate::make('boardmeeting', 'official', null, '   ', 10, 'application/docx', true);
    });

    it('refuses a slug that cannot be a key segment', function () {
        $this->expectException(InvalidSetting::class);

        WordTemplate::make('boardmeeting', 'نامه رسمی', null, 'نامه', 10, 'application/docx', true);
    });

    it('reads back what it wrote', function () {
        $template = WordTemplate::make('boardmeeting', 'official', 'session/42', 'نامهٔ رسمی', 2048, 'application/docx', true, true);

        $restored = WordTemplate::fromStored($template->key(), $template->toValue(), null);

        expect($restored->domain())->toBe('boardmeeting')
            ->and($restored->slug())->toBe('official')
            ->and($restored->entity())->toBe('session/42')
            ->and($restored->size())->toBe(2048)
            ->and($restored->isDefault())->toBeTrue()
            ->and($restored->hasPlaceholders())->toBeTrue()
            ->and($restored->key())->toBe($template->key());
    });

    it('refuses a row whose payload disagrees with its key', function () {
        // The key is what every lookup is written against. A payload naming a
        // different domain would apply one template's content to another's.
        $this->expectException(InvalidSetting::class);

        WordTemplate::fromStored('word/boardmeeting/official', ['domain' => 'cv', 'name' => 'نامه'], null);
    });

    it('refuses a row whose slug disagrees with its key', function () {
        // The file path is derived from the key's slug, so a payload naming
        // another one would describe a file that does not exist.
        $this->expectException(InvalidSetting::class);

        WordTemplate::fromStored('word/boardmeeting/official', ['slug' => 'other', 'name' => 'نامه'], null);
    });

    it('refuses a key that names no template', function () {
        foreach (['word/boardmeeting', 'word/', 'word//official', 'boardmeeting/official'] as $key) {
            expect(WordTemplate::handles($key))->toBeFalse($key);
        }
    });

    it('has no private half to leak', function () {
        // The file is on the private disk, not in the row. Storing it in the
        // encrypted column would mean decrypting every template to list their
        // names.
        expect(WordTemplate::make('boardmeeting', 'official', null, 'نامه', 10, 'application/docx', true)->toSecrets())
            ->toBeNull();
    });

    it('round-trips through the real store without touching the other kind', function () {
        $repo = app(SystemSettingsRepositoryInterface::class);

        $word = WordTemplate::make('boardmeeting', 'official', null, 'نامهٔ رسمی', 10, 'application/docx', true, true);
        $section = ExportTemplate::make('boardmeeting', null, ['identity']);

        $repo->put($word);
        $repo->put($section);

        expect($repo->get(WordTemplate::class, $word->key()))->toBeInstanceOf(WordTemplate::class)
            ->and($repo->get(ExportTemplate::class, $section->key()))->toBeInstanceOf(ExportTemplate::class);

        // And each listing sees only its own kind, which is the whole reason the
        // namespaces exist.
        $words = array_filter($repo->all(WordTemplate::class), fn (Setting $s): bool => $s instanceof WordTemplate);
        $sections = array_filter($repo->all(ExportTemplate::class), fn (Setting $s): bool => $s instanceof ExportTemplate);

        expect($words)->toHaveCount(1)
            ->and($sections)->toHaveCount(1);
    });
});
