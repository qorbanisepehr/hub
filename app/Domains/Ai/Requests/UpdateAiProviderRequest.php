<?php

namespace App\Domains\Ai\Requests;

use App\Domains\Ai\Enums\AiDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for updating an AI provider.
 *
 * Every field is `sometimes`, so an absent field is left alone rather than
 * nulled — the difference matters most for `api_key`. A write-only credential
 * is never sent back to the browser (§۶.5), so the form has nothing to put
 * in that box when editing, and treating the empty box as "clear the key"
 * would silently destroy a working credential on every unrelated edit.
 * Sending the key therefore *replaces* it and omitting it *keeps* it.
 *
 * Clearing is a separate `clear_api_key` flag for the same reason: an empty
 * box is what "untouched" looks like on the wire, so overloading it to mean
 * "delete" would put a credential's survival in the hands of how the browser
 * happens to serialize an untouched field.
 */
class UpdateAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'driver' => ['sometimes', Rule::enum(AiDriver::class)],
            'base_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'clear_api_key' => ['sometimes', 'boolean'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'headers.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
