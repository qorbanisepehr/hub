<?php

namespace Tests\Unit\Domains\Employee\Sections;

use App\Domains\Employee\Sections\SocialInsuranceSection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SocialInsuranceSectionTest extends TestCase
{
    private SocialInsuranceSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->section = new SocialInsuranceSection;
    }

    public function test_section_has_expected_key(): void
    {
        $this->assertSame(
            'social_insurance',
            $this->section->key()
        );
    }

    public function test_section_uses_expected_storage(): void
    {
        $this->assertSame(
            [
                'real' => ['insurance_number'],
                'jsonb' => 'section_social_insurance',
            ],
            $this->section->storage()
        );
    }

    public function test_prefill_contains_empty_history(): void
    {
        $this->assertSame(
            [
                'has_insurance_history' => false,
                'histories' => [],
            ],
            $this->section->prefill()
        );
    }

    public function test_valid_data_without_insurance_history_is_accepted(): void
    {
        $data = $this->validData([
            'has_insurance_history' => false,
            'histories' => [],
        ]);

        $this->assertValid($data);
    }

    public function test_null_histories_is_allowed_without_insurance_history(): void
    {
        $data = $this->validData([
            'has_insurance_history' => false,
            'histories' => null,
        ]);

        $this->assertValid($data);
    }

    public function test_valid_data_with_insurance_history_is_accepted(): void
    {
        $data = $this->validData([
            'has_insurance_history' => true,
            'histories' => [
                [
                    'workshop_name' => 'Company A',
                    'workshop_code' => '12345',
                    'job_title' => 'Developer',
                    'start_date' => Carbon::yesterday()->subYear()->toDateString(),
                    'end_date' => null,
                    'description' => null,
                ],
            ],
        ]);

        $this->assertValid($data);
    }

    public function test_insurance_history_requires_at_least_one_history_when_enabled(): void
    {
        $data = $this->validData([
            'has_insurance_history' => true,
            'histories' => [],
        ]);

        $this->assertInvalid($data, 'histories');
    }

    public function test_insurance_history_cannot_be_null_when_enabled(): void
    {
        $data = $this->validData([
            'has_insurance_history' => true,
            'histories' => null,
        ]);

        $this->assertInvalid($data, 'histories');
    }

    public function test_histories_must_be_empty_when_insurance_history_is_disabled(): void
    {
        $data = $this->validData([
            'has_insurance_history' => false,
            'histories' => [
                [
                    'workshop_name' => 'Company A',
                    'start_date' => Carbon::yesterday()->subYear()->toDateString(),
                ],
            ],
        ]);

        $this->assertInvalid($data, 'histories');
    }

    public function test_history_start_date_cannot_be_in_future(): void
    {
        $data = $this->validData([
            'has_insurance_history' => true,
            'histories' => [
                [
                    'workshop_name' => 'Company A',
                    'start_date' => Carbon::tomorrow()->toDateString(),
                    'end_date' => null,
                ],
            ],
        ]);

        $this->assertInvalid($data, 'histories.0.start_date');
    }

    public function test_history_end_date_cannot_be_in_future(): void
    {
        $data = $this->validData([
            'has_insurance_history' => true,
            'histories' => [
                [
                    'workshop_name' => 'Company A',
                    'start_date' => Carbon::yesterday()->subYear()->toDateString(),
                    'end_date' => Carbon::tomorrow()->toDateString(),
                ],
            ],
        ]);

        $this->assertInvalid($data, 'histories.0.end_date');
    }

    public function test_history_end_date_cannot_be_before_start_date(): void
    {
        $data = $this->validData([
            'has_insurance_history' => true,
            'histories' => [
                [
                    'workshop_name' => 'Company A',
                    'start_date' => '2025-01-01',
                    'end_date' => '2024-01-01',
                ],
            ],
        ]);

        $this->assertInvalid($data, 'histories.0.end_date');
    }

    public function test_history_without_end_date_is_valid(): void
    {
        $data = $this->validData([
            'has_insurance_history' => true,
            'histories' => [
                [
                    'workshop_name' => 'Company A',
                    'start_date' => Carbon::yesterday()->subYear()->toDateString(),
                    'end_date' => null,
                ],
            ],
        ]);

        $this->assertValid($data);
    }

    public function test_insurance_number_is_required_for_completion(): void
    {
        $data = $this->validData([
            'insurance_number' => null,
        ]);

        $this->assertInvalid($data, 'insurance_number');
    }

    public function test_insurance_status_is_required_for_completion(): void
    {
        $data = $this->validData([
            'insurance_status' => null,
        ]);

        $this->assertInvalid($data, 'insurance_status');
    }

    public function test_insurance_start_date_is_optional(): void
    {
        $data = $this->validData([
            'insurance_start_date' => null,
        ]);

        $this->assertValid($data);
    }

    private function validData(array $overrides = []): array
    {
        return array_replace_recursive([
            'insurance_number' => '1234567890',
            'insurance_status' => 'active',
            'insurance_start_date' => null,
            'has_insurance_history' => false,
            'histories' => [],
        ], $overrides);
    }

    private function assertValid(array $data): void
    {
        $validator = Validator::make(
            $data,
            $this->section->completionRules()
        );

        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson()
        );
    }

    private function assertInvalid(array $data, string $field): void
    {
        $validator = Validator::make(
            $data,
            $this->section->completionRules()
        );

        $this->assertTrue(
            $validator->fails(),
            'Expected validation to fail.'
        );

        $this->assertTrue(
            $validator->errors()->has($field),
            "Expected validation error for [{$field}]."
        );
    }
}
