<?php

namespace App\Domains\Audit\Requests;

use Illuminate\Contracts\Validation\Validator as ValidationValidator;
use Illuminate\Foundation\Http\FormRequest;

class StoreRetentionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:255'],
            'retention_days' => ['required', 'integer', 'min:1'],
            'archive_after_days' => ['nullable', 'integer', 'min:1'],
            'archive_enabled' => ['sometimes', 'boolean'],
            'delete_after_archive' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            if ($this->filled('archive_after_days')) {
                $retentionDays = $this->integer('retention_days');
                if ($retentionDays !== null && $this->integer('archive_after_days') > $retentionDays) {
                    $validator->errors()->add(
                        'archive_after_days',
                        'The archive after days must not be greater than the retention days.',
                    );
                }
            }

            if ($this->boolean('delete_after_archive') && ! $this->boolean('archive_enabled')) {
                $validator->errors()->add(
                    'delete_after_archive',
                    'The delete after archive option requires archiving to be enabled.',
                );
            }
        });
    }
}
