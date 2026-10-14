<?php

namespace App\Support\Exports;

use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\Value\Document\DocumentTable;

/**
 * The "what is in this archive" page, built from rows instead of entities.
 *
 * A bundle of forty meetings used to be a folder of unidentified files: forty
 * PDFs, a hundred scans, and nothing saying which meeting any of them belonged
 * to. Whoever opened it had to open files to find out.
 *
 * It is a DocumentSpec rather than a bespoke string, and that is the point: the
 * manifest goes through the same renderer as everything else, so the same list
 * can be emitted as Markdown for a person opening the archive and as JSON for a
 * machine reading it. The two always agree because they are one spec rendered
 * twice — a separately-written JSON index would be a second description of the
 * same thing, free to drift.
 *
 * Takes rows, never entities. What a row MEANS — a meeting, a CV, a set of
 * questionnaires — is the domain's business; this only lays rows out as labelled
 * sections with a table of files, which is the one shape every document in this
 * kernel already knows how to draw.
 */
final class BundleManifest
{
    /**
     * @param  list<array{label: string, description?: string|null, files: list<string>}>  $items
     *                                                                                             One entry per thing in the archive, each carrying the entry names
     *                                                                                             that thing contributed. A file that several items reference is
     *                                                                                             listed under each of them — the listing says what belongs together,
     *                                                                                             and deduplication of the bytes is the builder's business.
     * @param  string  $itemsLabel  Names what a row is, in the caller's language
     *                              ("meetings", "candidates") — the kernel knows
     *                              nothing about any of them.
     * @param  string  $filesLabel  Names the file column.
     */
    public static function forItems(
        string $title,
        string $itemsLabel,
        array $items,
        string $filesLabel = 'File',
    ): DocumentSpec {
        $sections = [];

        foreach ($items as $item) {
            $tables = [$item['files'] === []
                ? new DocumentTable('', ['—'], [])
                : new DocumentTable('', [$filesLabel], array_map(
                    static fn (string $file): array => [$file],
                    $item['files'],
                ))];

            $sections[] = new DocumentSection(
                heading: $item['label'],
                fields: ($item['description'] ?? null) === null
                    ? []
                    : [new DocumentField('', (string) $item['description'])],
                tables: $tables,
            );
        }

        return new DocumentSpec(
            title: $title,
            meta: [new DocumentField($itemsLabel, (string) count($items))],
            sections: $sections,
        );
    }
}
