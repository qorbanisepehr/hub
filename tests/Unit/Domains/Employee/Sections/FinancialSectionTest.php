<?php

namespace Tests\Unit\Domains\Employee\Sections;

use App\Domains\Employee\Sections\FinancialSection;
use App\Support\Sections\SectionDefinition;
use Illuminate\Contracts\Validation\Validator;
use Tests\TestCase;

class FinancialSectionTest extends TestCase
{
    private FinancialSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->section = new FinancialSection;
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function validateCompletion(array $account, string $mode = SectionDefinition::MODE_COMPLETION): Validator
    {
        return $this->section->validateData(
            ['bank_accounts' => [$account]],
            $mode,
        );
    }

    private function completeAccount(array $overrides = []): array
    {
        return array_merge([
            'bank_name' => 'ملت',
            'account_number' => '1234567890',
            'card_number' => '6037991123456786',
            'shaba_number' => 'IR830610000000000000000000',
        ], $overrides);
    }

    public function test_completion_accepts_a_luhn_valid_card_and_valid_iban(): void
    {
        $this->assertTrue($this->validateCompletion($this->completeAccount())->passes());
    }

    public function test_completion_rejects_a_card_with_a_broken_luhn_checksum(): void
    {
        $validator = $this->validateCompletion(
            $this->completeAccount(['card_number' => '6037991123456787']),
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            'financial.bank_accounts.0.card_number',
            $validator->errors()->toArray(),
        );
    }

    public function test_completion_rejects_a_non_irian_card_prefix(): void
    {
        $validator = $this->validateCompletion(
            $this->completeAccount(['card_number' => '4111111111111111']),
        );

        $this->assertFalse($validator->passes());
    }

    public function test_completion_rejects_an_iban_with_a_broken_mod97_checksum(): void
    {
        $validator = $this->validateCompletion(
            $this->completeAccount(['shaba_number' => 'IR000610000000000000000000']),
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            'financial.bank_accounts.0.shaba_number',
            $validator->errors()->toArray(),
        );
    }

    public function test_structural_mode_stays_draft_tolerant_for_half_entered_values(): void
    {
        // Drafts must not be blocked by format rules; they are enforced at
        // completion (submit) only.
        $validator = $this->validateCompletion(
            $this->completeAccount([
                'card_number' => '6037',
                'shaba_number' => 'IR8',
            ]),
            SectionDefinition::MODE_STRUCTURAL,
        );

        $this->assertTrue($validator->passes());
    }
}
