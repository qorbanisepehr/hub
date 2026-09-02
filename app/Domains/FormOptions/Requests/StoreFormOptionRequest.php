<?php

namespace App\Domains\FormOptions\Requests;

use App\Domains\FormOptions\Services\FormOptionService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormOptionRequest extends FormRequest
{
    public function __construct(
        private readonly FormOptionService $formOptionService,
        ...$args,
    ) {
        parent::__construct(...$args);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:50'],
            'value' => [
                'required',
                'string',
                'max:100',
                Rule::unique('form_options')->where('group', $this->input('group')),
            ],
            'label' => ['required', 'string', 'max:255'],
            ...$this->parentValueRules((string) $this->input('group')),
            'group_label' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Location invariant (v6 §46–47): a child group's parent_value must
     * reference an existing, active option of its parent group.
     *
     * @return array<int, mixed>
     */
    protected function parentValueRules(?string $group): array
    {
        return [
            'parent_value' => [
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail) use ($group): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $parentGroup = $this->formOptionService->parentGroupFor((string) $group);

                    if ($parentGroup === null || $this->formOptionService->isValid($parentGroup, $value)) {
                        return;
                    }

                    $fail("The {$attribute} must reference an active {$parentGroup} option.");
                },
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
