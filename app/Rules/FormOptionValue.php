<?php

namespace App\Rules;

use App\Domains\FormOptions\Services\FormOptionService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ensures a value is an active option of the given form-options group.
 *
 * Form sections persist the readable label (e.g. «تهران») rather than the
 * stable value key, so validation matches the label column. When a `$parentGroup`
 * is given, the value is treated as a combined place string
 * («{parentLabel}-{childLabel}», e.g. «تهران-تهران») and both parts must resolve.
 */
class FormOptionValue implements ValidationRule
{
    public function __construct(
        private readonly string $group,
        private readonly ?string $parentGroup = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->validate($attribute, $item, $fail);
            }

            return;
        }

        // Empty values mean "not provided" and are left to the required rules.
        if ($value === null || $value === '') {
            return;
        }

        $service = app(FormOptionService::class);
        $valid = is_string($value)
            && $this->parentGroup === null
                ? $service->isValidLabel($this->group, $value)
                : ($this->parentGroup === 'province'
                    ? $service->isValidCityPlace($value)
                    : false);

        if (! $valid) {
            $fail('مقدار انتخابی نامعتبر است.');
        }
    }
}
