<?php

namespace Tests\Unit\Support\Exports;

use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Writer\CsvWriter;
use App\Support\Exports\Writer\JsonlWriter;
use App\Support\Exports\Writer\TsvWriter;
use InvalidArgumentException;
use Tests\TestCase;

final class TabularWriterTest extends TestCase
{
    /** @return list<ExportColumn> */
    private function columns(): array
    {
        return [
            new ExportColumn('name', 'نام', 'Name'),
            new ExportColumn('salary', 'حقوق', 'Salary'),
        ];
    }

    /** @param  list<array<string, string|int|null>>  $rows */
    private function writeToString(
        CsvWriter|JsonlWriter|TsvWriter $writer,
        array $rows,
        ?ExportOptions $options = null,
    ): string {
        $stream = fopen('php://temp', 'r+');
        $writer->write($rows, $this->columns(), $options ?? new ExportOptions, $stream);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    public function test_csv_writes_rfc4180_quoting_and_crlf(): void
    {
        $csv = $this->writeToString(new CsvWriter, [
            ['name' => 'plain', 'salary' => '1000'],
            ['name' => 'with,comma', 'salary' => 'say "hi"'],
            ['name' => "line\nbreak", 'salary' => null],
        ]);

        $this->assertSame(
            "Name,Salary\r\n".
            "plain,1000\r\n".
            "\"with,comma\",\"say \"\"hi\"\"\"\r\n".
            "\"line\nbreak\",\r\n",
            $csv,
        );
    }

    public function test_csv_carries_persian_text_verbatim(): void
    {
        $csv = $this->writeToString(new CsvWriter, [
            ['name' => 'نقش اصلی', 'salary' => '۱۲۳'],
        ]);

        $this->assertStringContainsString('نقش اصلی', $csv);
        $this->assertStringContainsString('۱۲۳', $csv);
    }

    public function test_csv_omits_bom_by_default(): void
    {
        $csv = $this->writeToString(new CsvWriter, [
            ['name' => 'a', 'salary' => '1'],
        ]);

        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $csv);
    }

    public function test_csv_can_emit_bom_when_option_is_set(): void
    {
        $csv = $this->writeToString(new CsvWriter, [
            ['name' => 'متنی', 'salary' => '1'],
        ], new ExportOptions(bom: true));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_formula_guard_prefixes_dangerous_leading_characters(): void
    {
        $csv = $this->writeToString(new CsvWriter, [
            ['name' => '=HYPERLINK("http://evil", "click")', 'salary' => '-5'],
        ], new ExportOptions(formulaGuard: true));

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'-5", $csv);
    }

    public function test_formula_guard_leaves_safe_cells_untouched(): void
    {
        $csv = $this->writeToString(new CsvWriter, [
            ['name' => 'positive vibe', 'salary' => '+98 21 1234'],
        ], new ExportOptions(formulaGuard: true));

        $this->assertStringNotContainsString("'positive vibe", $csv);
    }

    public function test_tsv_uses_tab_delimiter_and_quotes_embedded_tabs(): void
    {
        $tsv = $this->writeToString(new TsvWriter, [
            ['name' => "col1\tcol2", 'salary' => 'plain'],
        ]);

        $this->assertSame(
            "Name\tSalary\r\n".
            "\"col1\tcol2\"\tplain\r\n",
            $tsv,
        );
    }

    public function test_jsonl_emits_one_json_object_per_line(): void
    {
        $jsonl = $this->writeToString(new JsonlWriter, [
            ['id' => 1, 'event' => 'created', 'سلام' => 'متن'],
            ['id' => 2, 'event' => 'updated', 'url' => 'https://x.test/a?b=1'],
        ]);

        $lines = explode(PHP_EOL, $jsonl);

        $this->assertSame(
            '{"id":1,"event":"created","سلام":"متن"}',
            $lines[0],
        );
        $this->assertSame(
            '{"id":2,"event":"updated","url":"https://x.test/a?b=1"}',
            $lines[1],
        );
    }

    public function test_jsonl_rejects_bom(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->writeToString(new JsonlWriter, [
            ['id' => 1],
        ], new ExportOptions(bom: true));
    }
}
