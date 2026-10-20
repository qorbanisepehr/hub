<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Support\Exports\Templates\FileTemplate;
use App\Support\Exports\Templates\FileTemplateStore;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\ListItem;

/**
 * Does Word draw ۱، ۲، ۳ — or bullets, or nothing at all?
 *
 * THE QUESTION NO EXISTING TEST ANSWERED. {@see RangeMacroTest} proved that a
 * repeated paragraph keeps its `<w:numPr>` and that `word/numbering.xml` is still in
 * the package. Both are necessary and neither is sufficient, and the gap between
 * them is where a broken export hides:
 *
 *   - A paragraph can keep its `numPr` while the `numId` it points at is gone or
 *     points somewhere else. The document OPENS PERFECTLY and prints no marker at
 *     all — no error, a plausible page, and a list that is not a list.
 *   - A paragraph can keep a `numId` that resolves to a BULLET definition, when
 *     the author asked for numbers. The document opens, prints markers, and they
 *     are the wrong shape.
 *
 * So this resolves the chain the whole way, in the real exported bytes rather than
 * in a part somebody picked out: paragraph -> `numId` -> `abstractNumId` -> the
 * `numFmt` and `lvlText` that decide what Word draws. A `numFmt` of `decimal` with
 * a `lvlText` of `%1.` is the difference between "۱." and a bullet, and it is the
 * only thing in the file that says which one Word will produce.
 *
 * IT GOES THROUGH THE ENDPOINT, not the filler directly, because the question is
 * about what a person downloads. The controller is thin, but the export it calls
 * is the one with a template attached, and that is the path that was never
 * exercised with a list in it.
 */
describe('a numbered list in an exported template', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    /**
     * The package's numbering, resolved all the way to what Word will draw.
     *
     * @return array{0: list<array{text: string, numId: string, format: ?string, levelText: ?string}>, 1: list<string>}
     */
    $resolved = function (string $docx): array {
        $zip = new ZipArchive;
        $zip->open($docx);

        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts[] = (string) $zip->getNameIndex($i);
        }

        $document = (string) $zip->getFromName('word/document.xml');
        $numbering = (string) $zip->getFromName('word/numbering.xml');
        $zip->close();

        // numId -> abstractNumId, the first hop.
        $toAbstract = [];
        preg_match_all(
            '/<w:num w:numId="(\d+)"[^>]*>\s*<w:abstractNumId w:val="(\d+)"\s*\/>/',
            $numbering,
            $numDefinitions,
            PREG_SET_ORDER
        );
        foreach ($numDefinitions as $definition) {
            $toAbstract[$definition[1]] = $definition[2];
        }

        // abstractNumId -> the format and marker at level 0, the second hop.
        $formats = [];
        preg_match_all(
            '/<w:abstractNum w:abstractNumId="(\d+)"[^>]*>(.*?)<\/w:abstractNum>/s',
            $numbering,
            $abstractDefinitions,
            PREG_SET_ORDER
        );
        foreach ($abstractDefinitions as $definition) {
            $body = $definition[2];

            // Only level 0. A definition has one `numFmt` per level, and the
            // paragraph's `ilvl` says which one applies.
            preg_match('/<w:lvl w:ilvl="0"[^>]*>(.*?)<\/w:lvl>/s', $body, $level);
            $levelBody = $level[1] ?? $body;

            preg_match('/<w:numFmt w:val="([^"]+)"\s*\/>/', $levelBody, $format);
            preg_match('/<w:lvlText w:val="([^"]*)"\s*\/>/', $levelBody, $levelText);

            $formats[$definition[1]] = [
                'format' => $format[1] ?? null,
                'levelText' => $levelText[1] ?? null,
            ];
        }

        $items = [];

        $dom = new DOMDocument;
        $dom->loadXML($document);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($xpath->query('//w:body//w:p') as $paragraph) {
            $numId = $xpath->query('.//w:numPr/w:numId', $paragraph);

            if ($numId->length === 0) {
                continue;
            }

            $id = (string) $numId->item(0)?->getAttribute('w:val');
            $text = '';

            foreach ($xpath->query('.//w:t', $paragraph) as $node) {
                $text .= $node->textContent;
            }

            $abstract = $toAbstract[$id] ?? null;
            $definition = $abstract === null ? null : ($formats[$abstract] ?? null);

            $items[] = [
                'text' => $text,
                'numId' => $id,
                'format' => $definition['format'] ?? null,
                'levelText' => $definition['levelText'] ?? null,
            ];
        }

        return [$items, $parts];
    };

    it('draws numbers, because the numId still points at a decimal definition', function () use ($resolved) {
        $meeting = BoardMeeting::factory()->create();
        $meeting->forceFill([
            'agenda' => [
                ['title' => 'بند اول', 'duration' => 15],
                ['title' => 'بند دوم', 'duration' => 20],
            ],
        ])->save();

        // A template with a real Word numbered list in it. The author wrote no
        // numbers at all — Word's numbering definition is what supplies them.
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('{{title}}');
        $section->addText('{{#agenda}}');
        $section->addListItem('{{agenda.title}}', 0, null, ['listType' => ListItem::TYPE_NUMBER]);
        $section->addText('{{/agenda}}');

        $path = tempnam(sys_get_temp_dir(), 'num-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $template = FileTemplate::make('docx', BoardMeetingSheetExport::TEMPLATE_DOMAIN, 'numbered', null, 'فهرست شماره‌دار', 10, '');
        app(FileTemplateStore::class)->write($template, (string) file_get_contents($path));
        app(SystemSettingsRepositoryInterface::class)->put($template);

        @unlink($path);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx?template=numbered")
            ->assertStatus(200);

        $out = tempnam(sys_get_temp_dir(), 'num-out-').'.docx';
        file_put_contents($out, $response->streamedContent());

        [$items, $parts] = $resolved($out);

        @unlink($out);

        // The list survived, as a list.
        expect($items)->toHaveCount(2)
            ->and($items[0]['text'])->toContain('بند اول')
            ->and($items[1]['text'])->toContain('بند دوم');

        // Every copy is the same list, so Word continues the count rather than
        // restarting it on each copy.
        expect(array_unique(array_column($items, 'numId')))->toHaveCount(1);

        // THE HOP THAT NOBODY CHECKED. The `numId` the paragraph carries must
        // still resolve, through the numbering part, to a DEFINITION THAT SAYS
        // "decimal". A list that kept its `numPr` but lost this would open
        // cleanly and print nothing at all.
        expect($items[0]['format'])->toBe('decimal')
            ->and($items[0]['levelText'])->toBe('%1.')
            ->and($items[1]['format'])->toBe('decimal');

        // And the part it points into is still in the package.
        expect($parts)->toContain('word/numbering.xml');
    });

    it('draws bullets for a bulleted list, and does not quietly number it', function () use ($resolved) {
        $meeting = BoardMeeting::factory()->create();
        $meeting->forceFill([
            'agenda' => [
                ['title' => 'بند اول', 'duration' => 15],
                ['title' => 'بند دوم', 'duration' => 20],
            ],
        ])->save();

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('{{title}}');
        $section->addText('{{#agenda}}');
        $section->addListItem('{{agenda.title}}', 0, null, ['listType' => ListItem::TYPE_BULLET_FILLED]);
        $section->addText('{{/agenda}}');

        $path = tempnam(sys_get_temp_dir(), 'bul-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $template = FileTemplate::make('docx', BoardMeetingSheetExport::TEMPLATE_DOMAIN, 'bulleted', null, 'فهرست گلوله‌ای', 10, '');
        app(FileTemplateStore::class)->write($template, (string) file_get_contents($path));
        app(SystemSettingsRepositoryInterface::class)->put($template);

        @unlink($path);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx?template=bulleted")
            ->assertStatus(200);

        $out = tempnam(sys_get_temp_dir(), 'bul-out-').'.docx';
        file_put_contents($out, $response->streamedContent());

        [$items] = $resolved($out);

        @unlink($out);

        expect($items)->toHaveCount(2)
            ->and($items[0]['format'])->toBe('bullet');
    });
});
