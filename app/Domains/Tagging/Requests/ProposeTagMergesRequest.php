<?php

namespace App\Domains\Tagging\Requests;

use App\Domains\Tagging\Enums\ConsolidationStrategy;
use App\Domains\Tagging\Value\ConsolidationFocus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for POST /api/tags/consolidate and its re-read.
 *
 * The focus is optional in both directions: a request with no body asks for
 * the whole vocabulary at the default strictness, which is what every earlier
 * caller meant by pressing «ادغام هوشمند». When the client does send one, the
 * re-read has to send the same one — the proposal is not stored, so `GET` is a
 * second run of the same question and a different `strategy` there would answer
 * a question nobody asked.
 *
 * `term` is a word, not a query: it narrows which tags the model sees, and is
 * capped short because a paragraph is not a focus.
 */
class ProposeTagMergesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Capability check is delegated to the permission middleware.
        return true;
    }

    /**
     * Validated whether the value arrives in a body or a query string, so the
     * queued ask and the re-read behind it enforce the same contract.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'strategy' => ['nullable', Rule::enum(ConsolidationStrategy::class)],
            'term' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function focus(): ConsolidationFocus
    {
        $strategy = $this->query('strategy', $this->input('strategy'));
        $term = $this->query('term', $this->input('term'));

        return new ConsolidationFocus(
            strategy: is_string($strategy)
                ? (ConsolidationStrategy::tryFrom($strategy) ?? ConsolidationStrategy::Balanced)
                : ConsolidationStrategy::Balanced,
            term: is_string($term) && trim($term) !== '' ? trim($term) : null,
        );
    }
}
