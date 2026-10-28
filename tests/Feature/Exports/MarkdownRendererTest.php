<?php

use App\Support\Exports\Render\MarkdownRenderer;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\Value\Document\DocumentTable;

/**
 * Markdown as a document format.
 *
 * The renderer is three short methods, so what matters is not that it produces
 * *something* but that it cannot be made to produce the wrong thing: a pipe or a
 * newline inside a value must not restructure the table around it, and a field
 * the domain gave no label to must not acquire one.
 */
describe('markdown document renderer', function () {
    /** Render a spec to the string the renderer would have written. */
    $render = function (DocumentSpec $spec): string {
        $stream = fopen('php://temp', 'r+');
        (new MarkdownRenderer)->render($spec, $stream);
        rewind($stream);
        $markdown = (string) stream_get_contents($stream);
        fclose($stream);

        return $markdown;
    };

    it('reports the extension and content type a markdown file needs', function () {
        $renderer = new MarkdownRenderer;

        expect($renderer->extension())->toBe('md')
            ->and($renderer->contentType())->toBe('text/markdown; charset=utf-8');
    });

    it('renders title, meta and section headings as markdown structure', function () use ($render) {
        // Expected values come from the same variables fed in, so a comparison
        // can never fail on an encoding difference between two literals of the
        // same Persian string.
        $subtitle = '۱۴۰۴/۰۶/۱۵ — جلسه ۴';
        $issued = '۱۴۰۴/۰۶/۱۵';

        $markdown = $render(new DocumentSpec(
            title: 'صورتجلسهٔ هیئت مدیره',
            subtitle: $subtitle,
            meta: [new DocumentField('تاریخ صدور', $issued)],
            sections: [
                new DocumentSection(
                    heading: 'حاضران',
                    tables: [new DocumentTable('', ['نام', 'نقش'], [['زهرا احمدی', 'رئیس']])],
                ),
            ],
        ));

        expect($markdown)
            ->toContain('# صورتجلسهٔ هیئت مدیره')
            ->toContain($subtitle)
            ->toContain('- **تاریخ صدور:** '.$issued)
            ->toContain('## حاضران')
            ->toContain('| نام | نقش |')
            ->toContain('| --- | --- |')
            ->toContain('| زهرا احمدی | رئیس |');
    });

    it('keeps a table\'s rows adjacent, because a blank line ends the table', function () use ($render) {
        $markdown = $render(new DocumentSpec(
            title: '',
            sections: [
                new DocumentSection(
                    heading: 'حاضران',
                    tables: [new DocumentTable('', ['نام', 'نقش'], [
                        ['زهرا احمدی', 'رئیس'],
                        ['مریم کاظمی', 'عضو'],
                        ['سمیرا نوری', 'دبیر'],
                    ])],
                ),
            ],
        ));

        // The whole table is ONE block. Separating its rows the way paragraphs
        // are separated is the single easiest way to emit markdown that looks
        // right in the source and renders as four unrelated pipe-shaped lines in
        // every reader — a blank line is where a GFM table ends.
        [$table] = array_values(array_filter(
            explode("\n\n", $markdown),
            fn (string $block): bool => str_contains($block, '| نام |'),
        ));

        expect($table)
            ->toContain('| نام | نقش |')
            ->toContain('| زهرا احمدی | رئیس |')
            ->toContain('| مریم کاظمی | عضو |')
            ->toContain('| سمیرا نوری | دبیر |')
            // Header, separator and three rows — five lines, no blanks between.
            ->and(count(array_filter(explode("\n", $table), fn (string $line): bool => trim($line) !== '')))->toBe(5)
            ->and($table)->not->toContain("\n\n");
    });

    it('keeps a pipe inside a value from breaking the row around it', function () use ($render) {
        $markdown = $render(new DocumentSpec(
            title: '',
            sections: [
                new DocumentSection(
                    heading: 'مصوبات',
                    tables: [new DocumentTable('', ['عنوان'], [['الف | ب']])],
                ),
            ],
        ));

        // One cell, one column: escaped rather than dropped, so the reader sees
        // what the title actually was instead of a silently restructured row.
        // Three table lines (header, separator, row) at two pipes each: a pipe
        // that leaked through would make it seven and split the cell in two.
        expect($markdown)->toContain('| الف &#124; ب |')
            ->and(substr_count($markdown, '|'))->toBe(6)
            // Adjacent, or it is not a table at all.
            ->and($markdown)->not->toContain("| --- | --- |\n\n");
    });

    it('folds a multi-line value into one cell instead of one row', function () use ($render) {
        $markdown = $render(new DocumentSpec(
            title: '',
            sections: [
                new DocumentSection(
                    heading: 'خلاصه',
                    tables: [new DocumentTable('', ['مورد'], [["خط اول\nخط دوم"]])],
                ),
            ],
        ));

        expect($markdown)->toContain('| خط اول<br>خط دوم |');
    });

    it('renders a field with no label as prose, not as a missing label', function () use ($render) {
        $markdown = $render(new DocumentSpec(
            title: '',
            sections: [
                new DocumentSection(
                    heading: 'صورتجلسه',
                    fields: [new DocumentField('', 'متن کامل صورتجلسه')],
                ),
            ],
        ));

        // The minutes body arrives with no label. Inventing an empty `**:**`
        // pair would put punctuation in the document the domain never wrote.
        expect($markdown)->toContain('متن کامل صورتجلسه')
            ->and($markdown)->not->toContain('****');
    });

    it('says a section has nothing in it rather than omitting the heading', function () use ($render) {
        $markdown = $render(new DocumentSpec(
            title: '',
            sections: [
                new DocumentSection(
                    heading: 'پیوست‌ها',
                    tables: [new DocumentTable('', ['نام فایل'], [])],
                ),
            ],
        ));

        expect($markdown)->toContain('## پیوست‌ها')
            ->toContain('—');
    });

    it('ends with exactly one newline, because a blank run reads as a mistake', function () use ($render) {
        $markdown = $render(new DocumentSpec(
            title: 'جلسه',
            sections: [new DocumentSection(heading: 'حاضران', fields: [new DocumentField('نام', 'زهرا')])],
        ));

        expect($markdown)->toEndWith("زهرا\n");
    });
});
