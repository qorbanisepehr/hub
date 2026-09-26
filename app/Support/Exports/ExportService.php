<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\ProvidesDetailSheets;
use App\Support\Exports\Contract\ProvidesOptionLabels;
use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\Contract\TemplateMetaProvider;
use App\Support\Exports\Contract\TemplateWriter;
use App\Support\Exports\Contract\WritesDetailSheets;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\Value\Document\DocumentTable;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportFile;
use App\Support\Exports\Value\ExportRequest;
use App\Support\Exports\Value\PresentationOptions;
use Illuminate\Support\Str;

/**
 * The kernel orchestrator: resolve the writer for the requested format, ask
 * the exporter which columns to emit, let the writer stream the bytes, and —
 * when the request opts in — shape headers and values for human readers.
 * No format logic lives here (the registry owns formats), and no domain
 * logic does (the exporter owns data, selection policy, and vocabulary).
 */
final class ExportService
{
    public function __construct(
        private readonly WriterRegistry $writers,
        private readonly DocumentRendererRegistry $documents,
    ) {}

    /**
     * @param  resource|null  $stream  Writable stream; defaults to php://temp so
     *                                 callers without an HTTP context (jobs, tests) still work.
     */
    public function run(TabularExporter $exporter, ExportRequest $request, $stream = null): ExportFile
    {
        if ($this->documents->has($request->format)) {
            return $this->runAsDocument($exporter, $request, $stream);
        }

        return $this->emit($exporter, $request, $exporter->rows($request), $stream, '', null);
    }

    /**
     * The document half of the pipeline: the exporter's selected columns and
     * rows (already presentation-shaped) become ONE table section of a
     * DocumentSpec, then the format's renderer serializes it. Documents are
     * in-memory by nature, so the whole row set is buffered here and guarded
     * against the sync row limit (422 territory for the controllers).
     */
    public function runAsDocument(TabularExporter $exporter, ExportRequest $request, $stream = null): ExportFile
    {
        $renderer = $this->documents->get($request->format);

        // Documents are for human readers BY DEFINITION: headers, Jalali
        // dates and Persian digits are forced regardless of what the request
        // asked, so a PDF never arrives in machine form. A Gregorian request
        // upgrades to Persian (the product's print calendar); 'both' keeps
        // its paired Gregorian + Jalali columns.
        $presentation = new PresentationOptions(
            headers: 'label',
            calendar: $request->presentation->calendar === 'gregorian' ? 'persian' : $request->presentation->calendar,
            digits: $request->presentation->digits === 'latin' ? 'persian' : $request->presentation->digits,
        );

        $columns = $this->withHeaderColumns($exporter->columnsFor($request), $presentation);
        $rows = [];
        $limit = (int) config('exports.sync_row_limit');
        $source = $this->presentedRows($exporter, $request, $exporter->rows($request), $columns, $presentation);

        foreach ($source as $row) {
            $rows[] = $row;

            if (count($rows) > $limit) {
                throw DocumentRowLimitExceeded::forLimit($limit);
            }
        }

        // 'both' calendars already emitted their Jalali sibling as its own
        // column through withHeaderColumns/presentedRows, exactly like the
        // streaming writers consume them.
        $table = new DocumentTable(
            caption: '',
            headers: array_map(fn (ExportColumn $column): string => $column->column, $columns),
            rows: array_map(
                fn (array $row): array => array_values(array_map(
                    static fn (ExportColumn $column): string => (string) ($row[$column->key] ?? ''),
                    $columns,
                )),
                $rows,
            ),
        );

        $spec = new DocumentSpec(
            title: $exporter->baseFilename(),
            sections: [new DocumentSection(heading: '', tables: [$table])],
            meta: array_values(array_filter([
                DocumentField::from((string) __('exports.generated_at'), now()->format('Y/m/d H:i')),
                DocumentField::from((string) __('exports.row_count'), (string) count($rows)),
            ])),
        );

        $stream ??= fopen('php://temp', 'r+');
        $renderer->render($spec, $stream);

        return new ExportFile(
            filename: ExportFilename::make($exporter->baseFilename(), $renderer->extension()),
            mimeType: $renderer->contentType(),
            stream: $stream,
        );
    }

    /**
     * A fill-and-import template: the exporter's default columns with zero
     * data rows. Emitted through the same pipeline as run() — identical file
     * shape and column order — so a filled template round-trips through the
     * M2 import validation unchanged. Templates stay in the machine form
     * (key headers, raw values) regardless of presentation options: their
     * reader is the import pipeline, not a human.
     *
     * When the chosen format can host a meta sheet (TemplateWriter) and the
     * exporter can describe one (TemplateMetaProvider), the template carries
     * its schema version and column labels on the extra sheet; other formats
     * degrade to a plain header-only file.
     *
     * @param  resource|null  $stream
     */
    public function template(TabularExporter $exporter, ExportRequest $request, $stream = null): ExportFile
    {
        $metaPairs = $exporter instanceof TemplateMetaProvider
            ? $exporter->templateMeta()
            : [];

        return $this->emit($exporter, $request, [], $stream, '-template', $metaPairs);
    }

    /**
     * Content-Disposition attachment filename for the HTTP response, with a
     * non-ASCII fallback (RFC 5987) since Persian bases are expected.
     */
    public function dispositionFilename(ExportFile $file): string
    {
        $ascii = Str::ascii($file->filename);

        if ($ascii === $file->filename) {
            return $file->filename;
        }

        return rawurlencode($file->filename);
    }

    /**
     * @param  iterable<array<string, string|int|float|bool|null>>  $rows
     * @param  array<string, string>|null  $metaPairs
     * @param  resource|null  $stream
     */
    private function emit(TabularExporter $exporter, ExportRequest $request, iterable $rows, $stream, string $filenameSuffix, ?array $metaPairs): ExportFile
    {
        $writer = $this->writers->get($request->format);
        $columns = $exporter->columnsFor($request);
        $stream ??= fopen('php://temp', 'r+');

        $presentation = $request->presentation;

        // Templates are machine-form BY DESIGN (their reader is the import
        // pipeline): presentation options apply to data exports only.
        $presentation = $metaPairs !== null
            ? new PresentationOptions
            : $presentation;

        if ($presentation->wantsPresentation()) {
            $columns = $this->withHeaderColumns($columns, $presentation);
            $rows = $this->presentedRows($exporter, $request, $rows, $columns, $presentation);
        }

        if ($metaPairs === null
            && $presentation->detailSheets
            && $exporter instanceof ProvidesDetailSheets
            && $writer instanceof WritesDetailSheets) {
            /** @var WritesDetailSheets $writer */
            $detailed = $presentation->wantsPresentation()
                ? new PresentedDetailExporter($exporter, $presentation)
                : $exporter;

            $writer->writeWithDetailSheets($rows, $columns, $detailed->detailSheets(), $detailed, $request->options, $stream);
        } elseif ($metaPairs !== null && $writer instanceof TemplateWriter) {
            // Human headers on the data sheet: the workbook is for a person
            // to fill, and the _meta sheet carries key→label so the import
            // reader translates the labels back onto the catalog. Values
            // stay import-safe (Gregorian dates, latin digits) — only the
            // header row is localized.
            $localized = array_map(
                fn (ExportColumn $column) => $column->headerFor('label'),
                $columns,
            );

            $writer->writeTemplate($rows, $localized, $request->options, $metaPairs, $stream);
        } else {
            $writer->write($rows, $columns, $request->options, $stream);
        }

        return new ExportFile(
            filename: ExportFilename::make($exporter->baseFilename().$filenameSuffix, $writer->extension()),
            mimeType: $writer->contentType(),
            stream: $stream,
        );
    }

    /**
     * Rewire each column's written header to the requested language without
     * disturbing the column KEYS (the rows are keyed by key; the writer only
     * reads ->column for the header row). With both calendars requested,
     * each Date column expands to its Gregorian + Jalali pair — header
     * order and row keys stay in lockstep through the seeded sibling slots.
     *
     * @param  list<ExportColumn>  $columns
     * @return list<ExportColumn>
     */
    private function withHeaderColumns(array $columns, PresentationOptions $presentation): array
    {
        $expanded = [];

        foreach ($columns as $column) {
            if ($presentation->calendar === 'both') {
                foreach ($column->columnsForCalendarShape() as $shaped) {
                    $expanded[] = $shaped->headerFor($presentation->headers);
                }

                continue;
            }

            $expanded[] = $column->headerFor($presentation->headers);
        }

        return $expanded;
    }

    /**
     * Shape row values through the ValuePresenter lazily, preserving the
     * exporter's streaming contract (rows transform as they are pulled).
     *
     * @param  list<ExportColumn>  $columns
     * @return iterable<array<string, string|int|float|bool|null>>
     */
    private function presentedRows(TabularExporter $exporter, ExportRequest $request, iterable $rows, array $columns, PresentationOptions $presentation): iterable
    {
        $columnsByKey = [];

        foreach ($columns as $column) {
            $columnsByKey[$column->key] = $column;
        }

        $presenter = new ValuePresenter(
            $exporter instanceof ProvidesOptionLabels ? $exporter : null,
        );

        foreach ($rows as $row) {
            // Both calendars: seed the empty Jalali sibling slot so the
            // presenter can split each date-shaped cell into the pair.
            if ($presentation->calendar === 'both') {
                foreach ($columns as $column) {
                    if ($column->bothSibling !== null) {
                        $row[$column->bothSibling->key] = null;
                    }
                }
            }

            yield $presenter->present($row, $columnsByKey, $presentation);
        }
    }
}
