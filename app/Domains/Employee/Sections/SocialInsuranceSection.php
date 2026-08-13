<?php

namespace App\Domains\Employee\Sections;

use App\Support\Sections\BaseSection;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;

class SocialInsuranceSection extends BaseSection
{
    public function key(): string
    {
        return 'social_insurance';
    }

    public function label(): string
    {
        return __('employee.sections.social_insurance');
    }

    public function fields(): array
    {
        return [
            'insurance_number' => 'string',
            'has_insurance_history' => 'boolean',
            'insurance_status' => 'string',
            'insurance_start_date' => 'date',
            'histories' => 'array',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'insurance_number' => 'nullable|string|max:30',

            'has_insurance_history' => 'nullable|boolean',

            'insurance_status' => 'nullable|string|max:100',

            'insurance_start_date' => 'nullable|date',

            'histories' => 'nullable|array',

            'histories.*.workshop_name' => 'nullable|string|max:255',
            'histories.*.workshop_code' => 'nullable|string|max:50',
            'histories.*.job_title' => 'nullable|string|max:255',
            'histories.*.start_date' => 'nullable|date',
            'histories.*.end_date' => 'nullable|date',
            'histories.*.description' => 'nullable|string|max:1000',
        ];
    }

    public function completionRules(): array
    {
        return [
            'insurance_number' => 'required|string|max:30',

            // TODO: Replace with the confirmed insurance status vocabulary.
            'insurance_status' => 'required|string|max:100',

            // Intentionally optional for now. It may later be removed if
            // histories[].start_date becomes the sole source of this concept.
            'insurance_start_date' => 'nullable|date',

            'has_insurance_history' => 'required|boolean',

            'histories' => 'required_if:has_insurance_history,true|array',
            //             'histories' => 'nullable|array',

            'histories.*.workshop_name' => 'required_if:has_insurance_history,true|string|max:255',
            'histories.*.workshop_code' => 'nullable|string|max:50',
            'histories.*.job_title' => 'nullable|string|max:255',
            'histories.*.start_date' => 'required_if:has_insurance_history,true|date',
            'histories.*.end_date' => 'nullable|date',
            'histories.*.description' => 'nullable|string|max:1000',

        ];
    }

    public function storage(): array
    {
        return [
            'real' => ['insurance_number'],
            'jsonb' => 'section_social_insurance',
        ];
    }

    public function searchMetadata(): array
    {
        return [
            'insurance_number',
        ];
    }

    public function prefill(): array
    {
        return [
            'has_insurance_history' => false,
            'histories' => [],
        ];
    }

    public function documentRequirements(): array
{
    return [
        'insurance-history' => [
            'required' => false,
            'max_files' => 1,
        ],
    ];
}
    protected function afterValidation(
        Validator $validator,
        array $data,
        string $mode
    ): void {
        $histories = $data['histories'] ?? [];

        if (! is_array($histories)) {
            return;
        }

        $today = Carbon::today();

        foreach ($histories as $index => $history) {
            if (! is_array($history)) {
                continue;
            }

            $startDate = $history['start_date'] ?? null;
            $endDate = $history['end_date'] ?? null;

            if ($startDate && Carbon::parse($startDate)->isAfter($today)) {
                $validator->errors()->add(
                    "{$this->key()}.histories.{$index}.start_date",
                    __('validation.before_or_equal', [
                        'attribute' => __('employee.sections.social_insurance.fields.start_date'),
                        'date' => $today->toDateString(),
                    ]),
                );
            }

            if ($endDate && Carbon::parse($endDate)->isAfter($today)) {
                $validator->errors()->add(
                    "{$this->key()}.histories.{$index}.end_date",
                    __('validation.before_or_equal', [
                        'attribute' => __('employee.sections.social_insurance.fields.end_date'),
                        'date' => $today->toDateString(),
                    ]),
                );
            }

            if (
                $startDate &&
                $endDate &&
                Carbon::parse($endDate)->isBefore(Carbon::parse($startDate))
            ) {
                $validator->errors()->add(
                    "{$this->key()}.histories.{$index}.end_date",
                    __('employee.sections.social_insurance.validation.end_date_before_start_date'),
                );
            }
        }
    }
}
