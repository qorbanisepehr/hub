<?php

namespace App\Domains\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'gif'])->max(2 * 1024),
            ],
        ];
    }
}
