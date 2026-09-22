<?php

namespace Tests\Unit\Support\Exports;

use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Writer\XlsxWriter;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

final class XlsxWriterTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    public function test_writes_typed_rows_that_read_back_as_values(): void
    {
        $path = $this->tempPath();
        $columns = [
            new ExportColumn('code', 'کد', 'code', ExportColumnType::Number),
            new ExportColumn('name', 'نام', 'name', ExportColumnType::Text),
            new ExportColumn('active', 'فعال', 'active', ExportColumnType::Boolean),
        ];

        (new XlsxWriter)->write(
            rows: [
                ['code' => 42, 'name' => 'کارمند یک', 'active' => true],
                ['code' => 7, 'name' => '=danger()', 'active' => false],
            ],
            columns: $columns,
            options: new ExportOptions,
            stream: fopen($path, 'w'),
        );

        $rows = $this->readSheet($path);

        $this->assertCount(3, $rows); // header + 2 data rows
        $this->assertSame(['code', 'name', 'active'], $rows[0]);

        // Numbers read back as numbers, text as text — even formula-looking text.
        $this->assertEqualsWithDelta(42.0, $rows[1][0], 0.0001);
        $this->assertSame('کارمند یک', $rows[1][1]);
        $this->assertTrue($rows[1][2]);
        $this->assertSame('=danger()', $rows[2][1]);
        $this->assertFalse($rows[2][2]);
    }

    public function test_headers_are_bold_and_null_values_are_empty_cells(): void
    {
        $path = $this->tempPath();

        (new XlsxWriter)->write(
            rows: [['name' => null, 'code' => 'x']],
            columns: [
                new ExportColumn('code', 'کد', 'code'),
                new ExportColumn('name', 'نام', 'name'),
            ],
            options: new ExportOptions,
            stream: fopen($path, 'w'),
        );

        // The v5 reader does not preserve styles, so boldness is asserted at
        // the zip level: the workbook must declare a bold font.
        $zip = new ZipArchive;
        $zip->open($path);
        $styles = (string) $zip->getFromName('xl/styles.xml');
        $zip->close();
        $this->assertStringContainsString('<b/>', $styles);

        $sheet = $this->firstSheet($path);
        $iterator = $sheet->getRowIterator();
        $iterator->rewind();

        $this->assertSame(['code', 'name'], array_values($iterator->current()->toArray()));

        $iterator->next();
        $this->assertSame(['x', ''], array_values($iterator->current()->toArray())); // empty cell → ''
    }

    public function test_data_sheet_is_named_for_the_import_reader(): void
    {
        $path = $this->tempPath();

        (new XlsxWriter)->write(
            rows: [],
            columns: [new ExportColumn('a', 'الف', 'a')],
            options: new ExportOptions,
            stream: fopen($path, 'w'),
        );

        $this->assertSame(['data'], $this->sheetNames($path));
    }

    public function test_template_writes_meta_sheet_after_data_sheet(): void
    {
        $path = $this->tempPath();
        $columns = [
            new ExportColumn('a', 'الف', 'a'),
            new ExportColumn('b', 'ب', 'b'),
        ];

        (new XlsxWriter)->writeTemplate(
            rows: [],
            columns: $columns,
            options: new ExportOptions,
            metaPairs: [
                '_schema_version' => '1',
                'a' => 'الف',
                'b' => 'ب',
            ],
            stream: fopen($path, 'w'),
        );

        $names = $this->sheetNames($path);
        $this->assertSame(['data', '_meta'], array_values($names));

        $metaRows = $this->readSheet($path, '_meta');
        $this->assertSame(['key', 'value'], $metaRows[0]);
        $this->assertContains(['_schema_version', '1'], $metaRows);
        $this->assertContains(['a', 'الف'], $metaRows);
        $this->assertContains(['b', 'ب'], $metaRows);

        // Template data sheet: headers only.
        $dataRows = $this->readSheet($path, 'data');
        $this->assertSame(['a', 'b'], $dataRows[0]);
        $this->assertCount(1, $dataRows);
    }

    public function test_rejects_bom_option(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new XlsxWriter)->write(
            rows: [],
            columns: [new ExportColumn('a', 'الف', 'a')],
            options: new ExportOptions(bom: true),
            stream: fopen('php://temp', 'r+'),
        );
    }

    public function test_content_type_and_extension(): void
    {
        $writer = new XlsxWriter;

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $writer->contentType(),
        );
        $this->assertSame('xlsx', $writer->extension());
    }

    private function tempPath(): string
    {
        return $this->tempFiles[] = tempnam(sys_get_temp_dir(), 'xlsx-writer-test-');
    }

    /** @return list<string> */
    private function sheetNames(string $path): array
    {
        $names = [];

        foreach ($this->firstReader($path)->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }

        return $names;
    }

    private function firstSheet(string $path): object
    {
        foreach ($this->firstReader($path)->getSheetIterator() as $sheet) {
            return $sheet;
        }

        $this->fail('The workbook has no sheets.');
    }

    /** @return list<list<string>> */
    private function readSheet(string $path, string $sheetName = 'data'): array
    {
        $rows = [];

        foreach ($this->firstReader($path)->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_values($row->toArray());
            }
        }

        return $rows;
    }

    private function firstReader(string $path): Reader
    {
        $reader = new Reader;
        $reader->open($path);

        $this->openReaders[] = $reader;

        return $reader;
    }

    /** @var list<Reader> */
    private array $openReaders = [];

    protected function tearDown(): void
    {
        foreach ($this->openReaders as $reader) {
            $reader->close();
        }

        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }
}
