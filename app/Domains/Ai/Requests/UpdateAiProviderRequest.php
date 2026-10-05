<?php

namespace App\Domains\Ai\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP boundary for updating an AI provider.
 *
 * Every field is `sometimes`, so an absent field is left alone rather than
 * nulled — the difference matters most for `api_key`. A write-only credential
 * is never sent back to the browser (§۶.5), so the form has nothing to put in
 * that box when editing, and treating the empty box as "clear the key" would
 * silently destroy a working credential on every unrelated edit. Sending the
 * key *replaces* it and omitting it *keeps* it; clearing is a separate
 * `clear_api_key` flag, because an empty box is what "untouched" looks like on
 * the wire and overloading it would put a credential's survival in the hands of
 * how the browser serializes an untouched field.
 *
 * `name` and `is_default` are absent on purpose. A name is the key and is
 * frozen; making a provider default is its own verb with its own route, so it
 * cannot be reached as a side effect of editing a URL.
 *
 * `driver` is frozen too, and gets no rule of its own: it decides the SDK class
 * that built this provider, so changing it would reinterpret a stored credential
 * under a different protocol rather than reconfigure it — an OpenAI key left
 * against an Anthropic driver is a provider that fails every call instead of one
 * that changes.
 *
 * It is ignored rather than `prohibited`, the opposite of the obvious choice:
 * the dialog echoes the row it was given, so `driver` is present on *every*
 * edit, and `prohibited` turned "change a provider's base URL" into a permanent
 * 422. The response carries the real `driver`, so the form re-renders with the
 * truth rather than with what it hoped.
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
            'base_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'clear_api_key' => ['sometimes', 'boolean'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'headers.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
