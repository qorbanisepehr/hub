<?php

namespace Tests\Unit\Support\Exports;

use App\Support\Exports\Contract\ProvidesOptionLabels;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\PresentationOptions;
use App\Support\Exports\Value\ValuePresentation;
use App\Support\Exports\ValuePresenter;
use Tests\TestCase;

final class ValuePresenterTest extends TestCase
{
    private const COLUMNS = [
        'personal_info.birth_date' => ['col' => 'birth', 'presentation' => ValuePresentation::Date],
        'employment.hire_date' => ['col' => 'hire', 'presentation' => ValuePresentation::Date],
        'personal_info.dependents_count' => ['col' => 'deps', 'presentation' => ValuePresentation::Number],
        'personal_info.marital_status' => ['col' => 'marital', 'presentation' => ValuePresentation::Raw],
        'education.is_student' => ['col' => 'student', 'presentation' => ValuePresentation::Boolean],
        'personal_info.first_name' => ['col' => 'name', 'presentation' => ValuePresentation::Raw],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // The product's user language; boolean labels come from the lang files.
        $this->app->setLocale('fa');
    }

    public function test_machine_form_is_untouched_without_presentation(): void
    {
        $presenter = new ValuePresenter(null);

        $row = $presenter->present($this->sampleRow(), $this->columnsByKey(), new PresentationOptions);

        $this->assertSame('2023-06-01', $row['employment.hire_date']);
        $this->assertSame(2, $row['personal_info.dependents_count']);
        $this->assertSame('married', $row['personal_info.marital_status']);
        // Booleans always label once the presenter runs; the machine/gate
        // decision itself lives in ExportService::wantsPresentation().
        $this->assertSame('بله', $row['education.is_student']);
    }

    public function test_persian_calendar_formats_jalali_dates(): void
    {
        $presenter = new ValuePresenter(null);
        $options = new PresentationOptions(calendar: 'persian');

        $row = $presenter->present($this->sampleRow(), $this->columnsByKey(), $options);

        // 2023-06-01 → 1402/03/11 (ASCII digits — they follow the digits option).
        $this->assertSame('1402/03/11', $row['employment.hire_date']);
        // Non-date-shaped strings in a Date column pass through untouched.
        $this->assertSame('نامشخص', $row['personal_info.birth_date']);
    }

    public function test_both_calendars_split_into_the_jalali_sibling_column(): void
    {
        $presenter = new ValuePresenter(null);
        $options = new PresentationOptions(calendar: 'both');

        // The row carries the empty sibling slot the pipeline seeds from the
        // expanded both-calendar columns.
        $row = $presenter->present(
            ['employment.hire_date' => '2023-06-01', 'employment.hire_date@jalali' => null],
            $this->columnsByKey(),
            $options,
        );

        // Gregorian cell keeps its import-safe machine value; the Jalali
        // sibling gets the Persian-calendar string.
        $this->assertSame('2023-06-01', $row['employment.hire_date']);
        $this->assertSame('1402/03/11', $row['employment.hire_date@jalali']);
    }

    public function test_both_calendars_leave_a_date_without_sibling_slot_untouched(): void
    {
        $presenter = new ValuePresenter(null);
        $options = new PresentationOptions(calendar: 'both');

        // A Date cell whose column has no sibling slot keeps its machine form
        // (the both-split only fires when the sibling column exists).
        $row = $presenter->present(
            ['employment.hire_date' => '2023-06-01'],
            $this->columnsByKey(),
            $options,
        );

        $this->assertSame('2023-06-01', $row['employment.hire_date']);
        $this->assertArrayNotHasKey('employment.hire_date@jalali', $row);
    }

    public function test_persian_digits_apply_to_dates_and_numbers(): void
    {
        $presenter = new ValuePresenter(null);
        $options = new PresentationOptions(calendar: 'persian', digits: 'persian');

        $row = $presenter->present($this->sampleRow(), $this->columnsByKey(), $options);

        $this->assertSame('۱۴۰۲/۰۳/۱۱', $row['employment.hire_date']);
        $this->assertSame('۲', $row['personal_info.dependents_count']);
    }

    public function test_option_labels_replace_stored_values_with_fallback(): void
    {
        $presenter = new ValuePresenter($this->fakeVocabulary(['personal_info.marital_status' => ['married' => 'متأهل']]));
        $options = new PresentationOptions;

        $row = $presenter->present($this->sampleRow(), $this->columnsByKey(), $options);

        $this->assertSame('متأهل', $row['personal_info.marital_status']);
        // Unknown option value falls back to the stored form.
        $this->assertSame('unknown_slug', $row['personal_info.first_name']);
    }

    public function test_persian_calendar_shapes_date_styled_raw_text_cells(): void
    {
        // military_status.from/to are stored as plain Y-m-d strings behind
        // `string` rules, so they arrive here as Text columns; human calendar
        // modes still owe the reader a Jalali date.
        $presenter = new ValuePresenter(null);
        $options = new PresentationOptions(calendar: 'persian');

        $row = $presenter->present(['personal_info.marital_status' => '2010-02-20'], $this->columnsByKey(), $options);

        $this->assertSame('1388/12/01', $row['personal_info.marital_status']);

        // Machine mode keeps the import-safe stored form untouched.
        $machine = $presenter->present(
            ['personal_info.marital_status' => '2010-02-20'],
            $this->columnsByKey(),
            new PresentationOptions,
        );

        $this->assertSame('2010-02-20', $machine['personal_info.marital_status']);
    }

    public function test_persian_digits_shape_raw_text_cells_without_option_vocabulary(): void
    {
        // The document builder presents without a vocabulary source; raw
        // cells (phones, codes) must still take the requested digit glyphs.
        $presenter = new ValuePresenter(null);
        $options = new PresentationOptions(calendar: 'persian', digits: 'persian');

        $row = $presenter->present(['personal_info.first_name' => '09123456789'], $this->columnsByKey(), $options);

        $this->assertSame('۰۹۱۲۳۴۵۶۷۸۹', $row['personal_info.first_name']);
    }

    public function test_generated_at_stamp_is_jalali_with_persian_digits(): void
    {
        $stamp = ValuePresenter::generatedAtStamp();

        // ۱۴۰۵/۰۷/۰۴ ۱۲:۲۰ — no Latin digits, no Gregorian year leak.
        $this->assertMatchesRegularExpression('/^[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2} [۰-۹]{2}:[۰-۹]{2}$/u', $stamp);
    }

    public function test_booleans_become_labels_from_the_lang_files(): void
    {
        $presenter = new ValuePresenter(null);
        $options = new PresentationOptions;

        $yes = $presenter->present(['education.is_student' => 1], $this->columnsByKey(), $options);
        $no = $presenter->present(['education.is_student' => 0], $this->columnsByKey(), $options);

        $this->assertSame('بله', $yes['education.is_student']);
        $this->assertSame('خیر', $no['education.is_student']);
    }

    public function test_null_and_empty_cells_pass_through(): void
    {
        $presenter = new ValuePresenter($this->fakeVocabulary([]));
        $options = new PresentationOptions(calendar: 'persian', digits: 'persian');

        $row = $presenter->present(
            ['employment.hire_date' => null, 'personal_info.marital_status' => ''],
            $this->columnsByKey(),
            $options,
        );

        $this->assertNull($row['employment.hire_date']);
        $this->assertSame('', $row['personal_info.marital_status']);
    }

    /**
     * @return array<string, ExportColumn>
     */
    private function columnsByKey(): array
    {
        $map = [];

        foreach (self::COLUMNS as $key => $spec) {
            $map[$key] = new ExportColumn($key, $key, $spec['col'], presentation: $spec['presentation']);
        }

        // The both-calendars Jalali sibling of the hire-date column, as the
        // pipeline's column expansion produces it.
        $map['employment.hire_date@jalali'] = new ExportColumn(
            'employment.hire_date@jalali',
            'تاریخ استخدام (شمسی)',
            'employment.hire_date@jalali',
            ExportColumnType::Text,
        );

        return $map;
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private function sampleRow(): array
    {
        return [
            'personal_info.birth_date' => 'نامشخص', // not a date shape — must pass through
            'employment.hire_date' => '2023-06-01',
            'personal_info.dependents_count' => 2,
            'personal_info.marital_status' => 'married',
            'education.is_student' => 1,
            'personal_info.first_name' => 'unknown_slug',
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $vocabulary
     */
    private function fakeVocabulary(array $vocabulary): ProvidesOptionLabels
    {
        return new class($vocabulary) implements ProvidesOptionLabels
        {
            public function __construct(
                private readonly array $vocabulary,
            ) {}

            public function optionLabel(string $columnKey, string $value): ?string
            {
                return $this->vocabulary[$columnKey][$value] ?? null;
            }
        };
    }
}
