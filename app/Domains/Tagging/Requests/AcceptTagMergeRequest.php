<?php

namespace App\Domains\Tagging\Requests;

use App\Domains\Tagging\Services\TagConsolidator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP boundary for POST /api/tags/consolidate/accept.
 *
 * The groups here are the ones the client chose from a proposal it was handed,
 * so on the happy path they echo slugs the hub just listed — but a request body
 * is a request body. The server re-validates every slug against the live
 * vocabulary in {@see TagConsolidator::merge()}
 * before it moves anything; this boundary only guarantees the shape, and
 * `present` keeps "merge nothing" a valid answer rather than a malformed one.
 *
 * `duplicates` is not `required`: an empty list is a group that does nothing,
 * and the service drops it. Refusing it at the boundary would turn a harmless
 * no-op into an error the caller has to know about.
 */
class AcceptTagMergeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Capability check is delegated to the permission middleware.
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'groups' => ['present', 'array', 'max:200'],
            'groups.*.canonical' => ['required', 'string', 'max:100'],
            'groups.*.duplicates' => ['present', 'array', 'max:100'],
            'groups.*.duplicates.*' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * The accepted groups, normalized to the shape the service reads.
     *
     * @return list<array{canonical: string, duplicates: list<string>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ((array) $this->input('groups', []) as $group) {
            if (! is_array($group)) {
                continue;
            }

            $groups[] = [
                'canonical' => (string) ($group['canonical'] ?? ''),
                'duplicates' => array_values(array_filter(
                    (array) ($group['duplicates'] ?? []),
                    static fn (mixed $slug): bool => is_string($slug) && $slug !== '',
                )),
            ];
        }

        return $groups;
    }
}
