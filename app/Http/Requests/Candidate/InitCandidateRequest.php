<?php

namespace App\Http\Requests\Candidate;

use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class InitCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The grant entity key this request targets, inferred from the route
     * prefix (e.g. `cv/init` -> `cv`, `questionnaire/init` -> `questionnaire`).
     * Drives whether `email` is required via config/grants.php.
     */
    protected function entityKey(): string
    {
        $path = $this->getPathInfo();

        foreach (array_keys(config('grants.entities')) as $entity) {
            if (str_contains($path, "/{$entity}/")) {
                return $entity;
            }
        }

        return 'questionnaire';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $emailRequired = (bool) config("grants.entities.{$this->entityKey()}.email_required", false);

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => $emailRequired
                ? ['required', 'email', 'max:255']
                : ['nullable', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
        ];
    }
}
