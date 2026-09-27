<?php

namespace App\Domains\Questionnaire\Exports;

use App\Domains\FormOptions\Services\FormOptionService;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use App\Support\Exports\Contract\DocumentSource;
use App\Support\Exports\Documents\SectionDocumentBuilder;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\ValuePresenter;

/**
 * The printable questionnaire document (PDF/Word): the candidate's full form
 * as one printed record. Section walk, translation-first labels and human
 * presentation belong to the shared SectionDocumentBuilder; the option
 * vocabulary resolves through the form-options table like every other
 * read-side pipeline.
 */
final class QuestionnaireDocument implements DocumentSource
{
    public function __construct(
        private readonly QuestionnaireService $sections,
        private readonly FormOptionService $formOptions,
    ) {}

    public function document(mixed $entity): DocumentSpec
    {
        /** @var Questionnaire $entity */
        $builder = new SectionDocumentBuilder(
            sections: $this->sections,
            labelMaps: [
                (array) trans('validation.attributes'),
            ],
            optionLabel: fn (string $key, string $value, ?string $group): ?string => $this->optionLabel($group, $value),
        );

        return new DocumentSpec(
            title: (string) __('questionnaire.document.title'),
            sections: $builder->build($entity),
            meta: array_values(array_filter([
                DocumentField::from((string) __('questionnaire.document.status'), $this->statusLabel($entity)),
                DocumentField::from((string) __('exports.generated_at'), ValuePresenter::generatedAtStamp()),
            ])),
            subtitle: trim("{$entity->first_name} {$entity->last_name}"),
        );
    }

    public function baseFilename(): string
    {
        return 'questionnaire';
    }

    private function statusLabel(Questionnaire $entity): ?string
    {
        $key = 'questionnaire.document.statuses.'.$entity->status;
        $label = (string) __($key);

        return $label === $key ? $entity->status : $label;
    }

    /**
     * Questionnaire option cells resolve through the form-options groups the
     * section rules declare; job-request enums (employment_type) live in the
     * same table, so there is no fixed in-list fallback here.
     */
    private function optionLabel(?string $group, string $value): ?string
    {
        if ($group === null) {
            return null;
        }

        $resolved = $this->formOptions->resolveValues($group, [$value]);

        return $resolved[0]['label'] ?? null;
    }
}
