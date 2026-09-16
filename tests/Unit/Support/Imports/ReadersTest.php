<?php

namespace Tests\Unit\Support\Imports;

use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Writer\CsvWriter;
use App\Support\Exports\Writer\XlsxWriter;
use App\Support\Imports\Reader\CsvReader;
use App\Support\Imports\Reader\XlsxReader;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxEngine;
use Tests\TestCase;

/**
 * Readers are exercised against files written by the export kernel — every
 * assertion here doubles as a round-trip check that the two kernels agree.
 */
final class ReadersTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_xlsx_reader_keys_data_rows_by_header(): void
    {
        $path = $this->writeXlsx();

        $rows = (new XlsxReader)->read($path)->all();

        $this->assertSame([
            ['code' => 42, 'name' => 'کارمند یک'],
            ['code' => 7, 'name' => '=danger()'],
        ], $rows);
    }

    public function test_xlsx_reader_pads_short_rows_and_truncates_long_ones(): void
    {
        $path = $this->tempPath();

        $engine = new XlsxEngine;
        $engine->openToFile($path);
        $engine->getCurrentSheet()->setName('data'); // The reader anchors on the data sheet.
        $engine->addRow(new Row([new StringCell('a'), new StringCell('b')]));
        $engine->addRow(new Row([new StringCell('1')])); // shorter than header
        $engine->addRow(new Row([new StringCell('2'), new StringCell('x'), new StringCell('extra')])); // longer
        $engine->close();

        $rows = (new XlsxReader)->read($path)->all();

        // Raw contract: empties arrive as '' (reader does not interpret);
        // the mapper turns '' into "absent". Truncation dropped 'extra'.
        $this->assertSame([
            ['a' => '1', 'b' => ''],
            ['a' => '2', 'b' => 'x'],
        ], $rows);
    }

    public function test_xlsx_reader_reads_template_meta_pairs(): void
    {
        $path = $this->tempPath();

        (new XlsxWriter)->writeTemplate(
            rows: [],
            columns: [new ExportColumn('code', 'کد', 'code')],
            options: new ExportOptions,
            metaPairs: ['_schema_version' => '1', 'code' => 'کد'],
            stream: fopen($path, 'w'),
        );

        $this->assertSame(
            ['_schema_version' => '1', 'code' => 'کد'],
            (new XlsxReader)->readMeta($path),
        );
    }

    public function test_xlsx_reader_returns_null_meta_for_plain_workbook(): void
    {
        $path = $this->writeXlsx();

        $this->assertNull((new XlsxReader)->readMeta($path));
    }

    public function test_csv_reader_keys_rows_by_header_and_strips_bom(): void
    {
        $path = $this->tempPath();

        (new CsvWriter)->write(
            rows: [
                ['code' => 1, 'name' => 'علی'],
                ['code' => 2, 'name' => ',quoted,'],
            ],
            columns: [
                new ExportColumn('code', 'کد', 'code', ExportColumnType::Number),
                new ExportColumn('name', 'نام', 'name', ExportColumnType::Text),
            ],
            options: new ExportOptions(bom: true), // Excel dialect — reader must strip it.
            stream: fopen($path, 'w'),
        );

        $rows = (new CsvReader)->read($path)->all();

        // The reader's contract is RAW values — strings, '' for empties.
        // Typing (Number → int) happens in the mapper, not here.
        $this->assertSame([
            ['code' => '1', 'name' => 'علی'],
            ['code' => '2', 'name' => ',quoted,'],
        ], $rows);
    }

    public function test_csv_reader_preserves_empty_middle_cells_as_null(): void
    {
        $path = $this->tempPath();

        (new CsvWriter)->write(
            rows: [
                ['a' => '1', 'b' => null, 'c' => '3'],
            ],
            columns: [
                new ExportColumn('a', 'آ', 'a'),
                new ExportColumn('b', 'ب', 'b'),
                new ExportColumn('c', 'پ', 'c'),
            ],
            options: new ExportOptions,
            stream: fopen($path, 'w'),
        );

        $rows = (new CsvReader)->read($path)->all();

        // Raw contract: empty cells are '' here; the MAPPER turns them into
        // "absent" (left out of the row) — tested in ImportMapperTest.
        $this->assertSame([
            ['a' => '1', 'b' => '', 'c' => '3'],
        ], $rows);
    }

    /**
     * A typed two-row workbook written by the export kernel.
     */
    private function writeXlsx(): string
    {
        $path = $this->tempPath();

        (new XlsxWriter)->write(
            rows: [
                ['code' => 42, 'name' => 'کارمند یک'],
                ['code' => 7, 'name' => '=danger()'],
            ],
            columns: [
                new ExportColumn('code', 'کد', 'code', ExportColumnType::Number),
                new ExportColumn('name', 'نام', 'name', ExportColumnType::Text),
            ],
            options: new ExportOptions,
            stream: fopen($path, 'w'),
        );

        return $path;
    }

    private function tempPath(): string
    {
        return $this->tempFiles[] = tempnam(sys_get_temp_dir(), 'import-reader-test-');
    }
}
