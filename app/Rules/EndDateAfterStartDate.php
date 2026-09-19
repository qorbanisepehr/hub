<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class EndDateAfterStartDate implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $startAttribute = preg_replace('/\.end_date$/', '.start_date', $attribute);

        if ($startAttribute === $attribute) {
            return;
        }

        $startDate = data_get($this->data, $startAttribute);

        if (empty($startDate)) {
            return;
        }

        if (strtotime((string) $value) < strtotime((string) $startDate)) {
            $fail(__('employee.social_insurance.validation.end_date_before_start_date'));
        }
    }
}
