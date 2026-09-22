<?php

namespace Tests\Unit\Support\Imports;

use App\Support\Exports\Value\ExportColumnType;
use App\Support\Imports\ImportMapper;
use App\Support\Imports\Value\ImportColumn;
use Tests\TestCase;

final class ImportMapperTest extends TestCase
{
    public function test_maps_headers_onto_accepted_columns_and_reports_unknowns(): void
    {
        $mapper = $this->mapper();

        $mapping = $mapper->mapHeaders([
            'personal_info.first_name',
            'extra_note',
            'employment.personnel_code',
            '',
        ]);

        $this->assertSame(
            ['personal_info.first_name', 'employment.personnel_code'],
            $mapping['matched'],
        );
        $this->assertSame(['extra_note'], $mapping['unknown']);
        $this->assertSame(['personal_info.id_number'], $mapping['missing_required']);
    }

    public function test_presentation_headers_are_unknown_not_matched(): void
    {
        $mapper = $this->mapper();

        $mapping = $mapper->mapHeaders(['نام', 'employment.personnel_code']);

        $this->assertSame(['employment.personnel_code'], $mapping['matched']);
        $this->assertSame(['نام'], $mapping['unknown']);
    }

    public function test_maps_row_with_typing_and_leaves_absent_columns_out(): void
    {
        $mapped = $this->mapper()->mapRow([
            'employment.personnel_code' => '123',
            'personal_info.first_name' => 'علی',
            'personal_info.children_count' => '2',
            'personal_info.is_active' => '1',
            'extra_note' => 'ignored',
        ]);

        // Numbers become native, booleans become bool, text stays string.
        $this->assertSame([
            'employment.personnel_code' => 123,
            'personal_info.first_name' => 'علی',
            'personal_info.children_count' => 2,
            'personal_info.is_active' => true,
        ], $mapped);

        // id_number is in the file? No — it is absent: not in the row at all.
        $this->assertArrayNotHasKey('personal_info.id_number', $mapped);
    }

    public function test_present_but_empty_cell_is_cleared_not_missing(): void
    {
        $mapped = $this->mapper()->mapRow([
            'employment.personnel_code' => '123',
            'personal_info.first_name' => null,
        ]);

        // The cell exists in the file but is empty → left out (unfilled),
        // NOT zero/false. Only the mapper's decision boundary matters here;
        // section validators own the "unfilled" semantics.
        $this->assertArrayNotHasKey('personal_info.first_name', $mapped);
        $this->assertSame(['employment.personnel_code' => 123], $mapped);
    }

    public function test_non_numeric_input_stays_string_for_number_columns(): void
    {
        $mapped = $this->mapper()->mapRow([
            'employment.personnel_code' => 'A-123',
            'personal_info.children_count' => 'two',
        ]);

        $this->assertSame('A-123', $mapped['employment.personnel_code']);
        $this->assertSame('two', $mapped['personal_info.children_count']);
    }

    /**
     * A small fixed catalog mirroring the real column shapes: text, number,
     * boolean, and a required anchor.
     *
     * @return list<ImportColumn>
     */
    private function columns(): array
    {
        return [
            new ImportColumn('employment.personnel_code', 'کد پرسنلی', ExportColumnType::Number),
            new ImportColumn('personal_info.first_name', 'نام', ExportColumnType::Text),
            new ImportColumn('personal_info.id_number', 'کد ملی', ExportColumnType::Text, required: true),
            new ImportColumn('personal_info.children_count', 'تعداد فرزند', ExportColumnType::Number),
            new ImportColumn('personal_info.is_active', 'فعال', ExportColumnType::Boolean),
        ];
    }

    private function mapper(): ImportMapper
    {
        return new ImportMapper($this->columns());
    }
}
