<?php

namespace App\Domains\Settings\Requests;

use App\Domains\Settings\Enums\BrandingImageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadBrandingImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->imageType();

        return [
            'file' => [
                'required',
                File::types($type?->config('allowed_types', ['jpg', 'jpeg', 'png', 'webp', 'svg']))
                    ->max($type?->config('max_size_kb', 2048) * 1024),
            ],
        ];
    }

    /**
     * The image kind implied by the upload URL (last path segment), or null
     * when the segment does not match a known branding image type.
     */
    private function imageType(): ?BrandingImageType
    {
        $segments = $this->segments();

        return BrandingImageType::tryFrom((string) end($segments));
    }
}
